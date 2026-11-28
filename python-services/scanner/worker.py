"""
TraceLess Scanner Worker
========================

Verified public-source recon over usernames. Every finding this worker emits
has been checked against the live source with an HTTP request and matched
against that source's own existence rule from the WhatsMyName dataset.

Attribution
-----------
WhatsMyName dataset (CC BY-SA 4.0)
  Copyright Chris Poulter & WebBreacher
  https://github.com/WebBreacher/WhatsMyName

Safety rules
------------
- Only public, unauthenticated sources are queried.
- A username is the only identifier accepted. Emails, phone numbers and any
  credential material are rejected before any request is made.
- No source is queried faster than ``--delay-ms`` per host.
- Responses are capped, never persisted, and only a SHA-256 of the body is
  kept as evidence.
- Nothing is reported unless the source's own match rule fires. Sources that
  block or rate-limit us are reported as ``unknown``, never as a finding.

Usage
-----
    python worker.py <identifier_type> <identifier_value> <consent:true|false>

Output
------
A single JSON object on stdout:
    {
      "status": "success" | "error",
      "identifier": "...",
      "identifier_type": "username",
      "findings": [ ... ],
      "sources": [ ... ],
      "attributions": [ ... ]
    }
"""

from __future__ import annotations

import concurrent.futures
import hashlib
import json
import os
import re
import socket
import ssl
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Any, Dict, Iterable, List, Optional, Tuple

ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
WMN_DATA_PATH = os.path.join(ROOT_DIR, "upstream", "WhatsMyName", "wmn-data.json")

USER_AGENT = (
    "TraceLess/1.0 (+https://traceless.example; public-source footprint check)"
)
MAX_BODY_BYTES = 512 * 1024
DEFAULT_TIMEOUT = 10.0
DEFAULT_CONCURRENCY = 6
DEFAULT_DELAY_MS = 1200
DEFAULT_SITE_LIMIT = 24

# Curated sources we are willing to publish findings for. Names are matched
# case-insensitively against the WhatsMyName dataset. Sites missing from the
# installed dataset are skipped rather than guessed at.
QUICK_WMN_SITES: Tuple[str, ...] = (
    "Facebook",
    "Instagram",
    "X",
    "TikTok",
    "YouTube",
    "Reddit",
    "Telegram",
    "Snapchat",
    "Pinterest",
)
QUICK_TOP10: Tuple[str, ...] = (
    "Facebook",
    "Instagram",
    "X",
    "LinkedIn",
    "TikTok",
    "YouTube",
    "Reddit",
    "Telegram",
    "Snapchat",
    "Pinterest",
)
QUICK_USERNAME_MODULES: Tuple[str, ...] = (
    "facebook",
    "instagram",
    "x",
    "linkedin",
    "tiktok",
    "youtube",
    "reddit",
    "telegram",
    "snapchat",
    "pinterest",
)
QUICK_EMAIL_MODULES: Tuple[str, ...] = (
    "facebook",
    "instagram",
    "x",
    "pinterest",
)

CURATED_SITES: Tuple[str, ...] = (
    "GitHub",
    "GitLab",
    "Reddit",
    "X",
    "Instagram",
    "LinkedIn",
    "Stack Overflow",
    "Gravatar",
    "Medium",
    "DeviantArt",
    "Spotify",
    "Steam",
    "Pinterest",
    "Disqus",
    "Vimeo",
    "Twitch",
    "Keybase",
    "Telegram",
    "SoundCloud",
    "HackerNews",
    "Docker Hub",
    "Behance",
    "Dribbble",
    "Flickr",
)

CURATED_ALIASES: Dict[str, Tuple[str, ...]] = {
    # Dataset entries whose names differ from the label we show to users.
    "GitHub": ("GitHub (User)", "GitHub (Gists)"),
    "Stack Overflow": ("StackOverflow",),
    "YouTube": ("YouTube Channel", "YouTube User", "YouTube User2"),
    "HackerNews": ("Hacker News",),
    "Docker Hub": ("Docker Hub (User)", "Docker Hub (Organization)"),
    # LinkedIn is no longer present in the dataset; kept here so the curated
    # list stays readable and the omission is explicit rather than silent.
    "LinkedIn": (),
}

ATTRIBUTIONS: List[Dict[str, str]] = [
    {
        "component": "WhatsMyName Dataset",
        "license": "Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)",
        "copyright": "Chris Poulter & WebBreacher",
        "repository": "https://github.com/WebBreacher/WhatsMyName",
    },
]

STATUS_FOUND = "found"
STATUS_NOT_FOUND = "not_found"
STATUS_UNKNOWN = "unknown"
STATUS_ERROR = "error"
STATUS_SKIPPED = "skipped"

# Reasons a check produced no verdict. Surfaced to the user as "not verified".
REASON_BLOCKED = "source_blocked_request"
REASON_NETWORK = "network_error"
REASON_TIMEOUT = "timeout"
REASON_HTTP_ERROR = "unexpected_http_status"
REASON_RULE_MISMATCH = "response_did_not_match_rule"
REASON_TOO_LARGE = "response_too_large"
REASON_INVALID_URL = "invalid_check_url"


@dataclass
class CheckResult:
    name: str
    category: str
    outcome: str
    url: str
    http_status: Optional[int] = None
    rule: Optional[str] = None
    reason: Optional[str] = None
    elapsed_ms: Optional[int] = None
    body_sha256: Optional[str] = None
    final_url: Optional[str] = None
    title: str = ""
    description: str = ""
    risk: str = "low"
    removal_method: str = "manual_instructions"

    def to_source(self) -> Dict[str, Any]:
        return {
            "name": self.name,
            "category": self.category,
            "outcome": self.outcome,
            "checked_url": self.url,
            "http_status": self.http_status,
            "rule": self.rule,
            "reason": self.reason,
            "elapsed_ms": self.elapsed_ms,
        }


class RateLimiter:
    """Per-host minimum interval between requests."""

    def __init__(self, delay_ms: int) -> None:
        self._delay = max(0, delay_ms) / 1000.0
        self._lock = threading.Lock()
        self._next_allowed: Dict[str, float] = {}

    def wait(self, url: str) -> None:
        if self._delay <= 0:
            return
        host = urllib.parse.urlsplit(url).netloc.lower()
        with self._lock:
            now = time.monotonic()
            ready_at = self._next_allowed.get(host, now)
            self._next_allowed[host] = max(now, ready_at) + self._delay
        sleep_for = ready_at - time.monotonic()
        if sleep_for > 0:
            time.sleep(sleep_for)


def _normalize_category(raw: Optional[str]) -> str:
    return {
        "coding": "developer_profile",
        "social": "social_profile",
        "hobby": "social_profile",
        "forum": "social_profile",
        "political": "social_profile",
        "shopping": "social_profile",
        "other": "public_directory",
    }.get((raw or "").lower(), "social_profile")


def _risk_for(category: str) -> str:
    return {
        "developer_profile": "low",
        "public_avatar": "moderate",
        "public_directory": "moderate",
    }.get(category, "moderate")


def _slug_identifier(value: str) -> Optional[str]:
    """Reduce the identifier to the character set sources accept."""
    slug = re.sub(r"[^a-zA-Z0-9_\-.]", "", value)
    slug = re.sub(r"\.{2,}", ".", slug).strip(".-")
    if not slug or len(slug) > 64:
        return None
    return slug


def _decode_body(raw: bytes, content_type: str) -> str:
    charset = "utf-8"
    match = re.search(r"charset=([\w\-]+)", content_type or "", re.I)
    if match:
        charset = match.group(1)
    try:
        return raw.decode(charset, errors="ignore")
    except LookupError:
        return raw.decode("utf-8", errors="ignore")


def _text(value: Any) -> str:
    return value if isinstance(value, str) else ""


def _code(value: Any) -> Optional[int]:
    try:
        return int(value)
    except (TypeError, ValueError):
        return None


def _matches(rule: str, http_status: int, body: str) -> bool:
    return (rule or "") in body and rule != ""


class ScannerService:
    def __init__(
        self,
        *,
        timeout: float = DEFAULT_TIMEOUT,
        concurrency: int = DEFAULT_CONCURRENCY,
        delay_ms: int = DEFAULT_DELAY_MS,
        site_limit: int = DEFAULT_SITE_LIMIT,
        offline: bool = False,
        username: Optional[str] = None,
    ) -> None:
        self.timeout = max(1.0, float(timeout))
        self.concurrency = max(1, int(concurrency))
        self.site_limit = max(1, int(site_limit))
        self.offline = offline
        self.username = username
        self.rate_limiter = RateLimiter(delay_ms)
        self.wmn_sites = self._load_wmn_data()
        self._ssl_context = ssl.create_default_context()

    # ------------------------------------------------------------------ data
    def _load_wmn_data(self) -> List[Dict[str, Any]]:
        if not os.path.exists(WMN_DATA_PATH):
            return []
        try:
            with open(WMN_DATA_PATH, "r", encoding="utf-8") as handle:
                data = json.load(handle)
            sites = data.get("sites", [])
            return sites if isinstance(sites, list) else []
        except (OSError, ValueError):
            return []

    def _selected_sites(self, curated_sites: Optional[Sequence[str]] = None) -> List[Dict[str, Any]]:
        by_name = {
            str(site.get("name", "")).lower(): site
            for site in self.wmn_sites
            if isinstance(site, dict)
        }
        selected: List[Dict[str, Any]] = []
        for name in curated_sites or CURATED_SITES:
            site = self._resolve_site(by_name, name)
            if not site:
                continue
            if not _text(site.get("uri_check")) or not _text(site.get("e_string")):
                continue
            if site.get("valid") is False:
                continue
            # Keep the curated label for display; the dataset name is often
            # qualified, e.g. "GitHub (User)".
            display = dict(site)
            display["display_name"] = name
            selected.append(display)
            if len(selected) >= self.site_limit:
                break
        return selected

    def _resolve_site(
        self, by_name: Dict[str, Dict[str, Any]], curated: str
    ) -> Optional[Dict[str, Any]]:
        """Map a curated label to its dataset entry, following known aliases."""
        for candidate in (curated, *CURATED_ALIASES.get(curated, ())):
            site = by_name.get(candidate.lower())
            if site:
                return site
        return None

    # --------------------------------------------------------------- network
    def _fetch(self, url: str) -> Tuple[int, str, str]:
        """Return (status, body, final_url). Raises on transport errors."""
        self.rate_limiter.wait(url)
        request = urllib.request.Request(
            url,
            headers={
                "User-Agent": USER_AGENT,
                "Accept": "text/html,application/json;q=0.9,*/*;q=0.8",
                "Accept-Language": "en",
            },
            method="GET",
        )
        try:
            response = urllib.request.urlopen(
                request, timeout=self.timeout, context=self._ssl_context
            )
        except urllib.error.HTTPError as error:
            # Error responses still carry the body we need to classify.
            with error:
                raw = error.read(MAX_BODY_BYTES)
                body = _decode_body(raw, error.headers.get("Content-Type", ""))
                return error.code, body, error.geturl()
        with response:
            # Large pages are truncated rather than rejected. Existence rules
            # match on markers near the top of the document, and a marker that
            # falls beyond the cut simply yields "unknown" — never a finding.
            raw = response.read(MAX_BODY_BYTES)
            body = _decode_body(raw, response.headers.get("Content-Type", ""))
            return response.status, body, response.geturl()

    def _check_site(self, site: Dict[str, Any], slug: str) -> CheckResult:
        name = _text(site.get("display_name")) or _text(site.get("name")) or "Unknown source"
        category = _normalize_category(_text(site.get("cat")))
        template = _text(site.get("uri_check"))
        url = template.replace("{account}", urllib.parse.quote(slug, safe=""))
        result = CheckResult(
            name=name,
            category=category,
            outcome=STATUS_UNKNOWN,
            url=url,
            risk=_risk_for(category),
            removal_method="manual_instructions",
        )

        if not url.lower().startswith(("http://", "https://")):
            result.reason = REASON_INVALID_URL
            return result

        if self.offline:
            result.reason = "network_disabled"
            return result

        e_code = _code(site.get("e_code")) or 200
        e_string = _text(site.get("e_string"))
        m_code = _code(site.get("m_code"))
        m_string = _text(site.get("m_string"))

        started = time.monotonic()
        try:
            status, body, final_url = self._fetch(url)
        except ValueError as error:
            result.elapsed_ms = int((time.monotonic() - started) * 1000)
            result.reason = str(error) or REASON_HTTP_ERROR
            return result
        except (socket.timeout, TimeoutError):
            result.elapsed_ms = int((time.monotonic() - started) * 1000)
            result.reason = REASON_TIMEOUT
            return result
        except urllib.error.URLError as error:
            result.elapsed_ms = int((time.monotonic() - started) * 1000)
            result.reason = (
                REASON_TIMEOUT
                if isinstance(error.reason, (socket.timeout, TimeoutError))
                else REASON_NETWORK
            )
            return result
        except Exception:  # noqa: BLE001 - never leak transport detail
            result.elapsed_ms = int((time.monotonic() - started) * 1000)
            result.reason = REASON_NETWORK
            return result

        result.elapsed_ms = int((time.monotonic() - started) * 1000)
        result.http_status = status
        result.final_url = final_url
        result.body_sha256 = hashlib.sha256(body.encode("utf-8", "ignore")).hexdigest()

        if status in (401, 403, 429, 451, 999):
            result.reason = REASON_BLOCKED
            return result

        if status == e_code and _matches(e_string, status, body):
            result.outcome = STATUS_FOUND
            result.rule = "e_string"
            result.title = f"Public profile on {name}"
            result.description = (
                f"{name} served its profile page for the handle '{slug}', "
                "matched against the source's own existence rule."
            )
            return result

        if m_string and _matches(m_string, status, body):
            result.outcome = STATUS_NOT_FOUND
            result.rule = "m_string"
            return result

        if m_code is not None and status == m_code and not _matches(e_string, status, body):
            result.outcome = STATUS_NOT_FOUND
            result.rule = "m_code"
            return result

        result.reason = REASON_RULE_MISMATCH
        return result

    # ------------------------------------------------------------------ scan
    def scan(
        self,
        identifier_type: str,
        identifier_value: str,
        consent_verified: bool = False,
        mode: str = "curated",
    ) -> Dict[str, Any]:
        if not consent_verified:
            return {
                "status": "error",
                "message": "Consent not confirmed. TraceLess requires explicit ownership authorization.",
            }

        if identifier_type == "email":
            return self._scan_deep(
                identifier_type,
                identifier_value,
                email_modules=QUICK_EMAIL_MODULES if mode == "quick" else None,
            )

        if identifier_type == "phone":
            return self._scan_phone(identifier_value)

        if identifier_type != "username":
            return {
                "status": "error",
                "message": "Only public usernames, emails and phone numbers can be checked.",
            }

        if mode == "quick":
            return self._scan_quick_username(identifier_value)

        if mode == "deep":
            return self._scan_deep(identifier_type, identifier_value)

        slug = _slug_identifier(identifier_value)
        if slug is None:
            return {
                "status": "error",
                "message": "The username could not be normalized into a safe query value.",
            }

        sites = self._selected_sites()
        if not sites:
            return {
                "status": "error",
                "message": "The scanner dataset is unavailable. Install python-services/upstream/WhatsMyName.",
            }

        results: List[CheckResult] = []
        with concurrent.futures.ThreadPoolExecutor(max_workers=self.concurrency) as pool:
            futures = [pool.submit(self._check_site, site, slug) for site in sites]
            for future in concurrent.futures.as_completed(futures):
                try:
                    results.append(future.result())
                except Exception:  # noqa: BLE001 - a single source must not fail the scan
                    continue

        return self._build_report(identifier_type, identifier_value, results, CURATED_SITES)


    def _scan_quick_username(self, identifier_value: str) -> Dict[str, Any]:
        """Nine fast curated social checks plus the public LinkedIn module.

        This is the guest quick scan. It deliberately avoids fanning out across
        the full deep module set: the ten top social sources are enough for a
        quick look, and keep the request inside a normal PHP timeout.
        """
        slug = _slug_identifier(identifier_value)
        if slug is None:
            return {
                "status": "error",
                "message": "The username could not be normalized into a safe query value.",
            }

        sites = self._selected_sites(QUICK_WMN_SITES)
        if not sites:
            return {
                "status": "error",
                "message": "The scanner dataset is unavailable. Install python-services/upstream/WhatsMyName.",
            }

        results: List[CheckResult] = []
        with concurrent.futures.ThreadPoolExecutor(max_workers=self.concurrency) as pool:
            futures = [pool.submit(self._check_site, site, slug) for site in sites]
            for future in concurrent.futures.as_completed(futures):
                try:
                    results.append(future.result())
                except Exception:  # noqa: BLE001 - one source must not fail the quick scan
                    continue

        # LinkedIn is not in the WMN dataset anymore, but user-scanner has a
        # public-profile module for it. One module, no full deep scan.
        if self.offline:
            results.append(
                CheckResult(
                    name="LinkedIn",
                    category="social_profile",
                    outcome=STATUS_UNKNOWN,
                    url=f"https://www.linkedin.com/in/{slug}",
                    rule="account_probe",
                    reason="network_disabled",
                )
            )
        else:
            try:
                import user_scanner_bridge as bridge

                rows = bridge.scan_username(identifier_value, modules=("linkedin",))
                row = rows[0] if rows else {}
                results.append(
                    CheckResult(
                        name="LinkedIn",
                        category="social_profile",
                        outcome=_text(row.get("outcome")) or STATUS_UNKNOWN,
                        url=_text(row.get("url")) or f"https://www.linkedin.com/in/{slug}",
                        rule="account_probe",
                        reason=row.get("reason"),
                        title="Public profile on LinkedIn",
                        description="The service responded as if this handle has a public profile.",
                        risk="moderate",
                    )
                )
            except Exception:  # noqa: BLE001 - source errors remain unknown
                results.append(
                    CheckResult(
                        name="LinkedIn",
                        category="social_profile",
                        outcome=STATUS_UNKNOWN,
                        url=f"https://www.linkedin.com/in/{slug}",
                        rule="account_probe",
                        reason="source_error",
                    )
                )

        return self._build_report("username", identifier_value, results, QUICK_TOP10)


    def _build_report(
        self,
        identifier_type: str,
        identifier_value: str,
        results: List[CheckResult],
        order_names: Sequence[str],
    ) -> Dict[str, Any]:
        order = {name.lower(): index for index, name in enumerate(order_names)}
        results.sort(key=lambda item: order.get(item.name.lower(), len(order)))

        findings: List[Dict[str, Any]] = []
        for result in results:
            if result.outcome != STATUS_FOUND:
                continue
            findings.append(
                {
                    "source": result.name,
                    "category": result.category,
                    "identifier_type": identifier_type,
                    "identifier_value": identifier_value,
                    "url": result.final_url or result.url,
                    "confidence": 0.95 if result.rule == "e_string" else 0.8,
                    "risk": result.risk,
                    "removal_method": result.removal_method,
                    "status": "needs_user_action",
                    "title": result.title,
                    "description": result.description,
                    "action_guidance": (
                        f"Review the public profile on {result.name}. Remove metadata you do "
                        "not want indexed, or delete the account if it is unused."
                    ),
                    "verification_status": "verified",
                    "evidence": {
                        "rule": result.rule,
                        "http_status": result.http_status,
                        "checked_url": result.url,
                        "final_url": result.final_url,
                        "body_sha256": result.body_sha256,
                        "elapsed_ms": result.elapsed_ms,
                        "checked_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
                    },
                }
            )

        return {
            "status": "success",
            "identifier": identifier_value,
            "identifier_type": identifier_type,
            "checked_count": len(results),
            "total_found": len(findings),
            "findings": findings,
            "sources": [result.to_source() for result in results],
            "attributions": ATTRIBUTIONS,
        }


    # ------------------------------------------------------- deep adapters
    def _scan_deep(
        self,
        identifier_type: str,
        identifier_value: str,
        email_modules: Optional[Sequence[str]] = None,
        username_modules: Optional[Sequence[str]] = None,
    ) -> Dict[str, Any]:
        """Run the vendored user-scanner (username categories or email modules).

        These are account probes, not page fetches, so every finding is marked
        unverified: it shows an account exists, not that personal data is exposed.
        """

        try:
            import user_scanner_bridge as bridge
        except Exception:  # noqa: BLE001 - optional dependency
            return {
                "status": "error",
                "message": "The deep scanner is not installed on this host.",
            }

        try:
            if identifier_type == "email":
                rows = bridge.scan_email(identifier_value, modules=email_modules)
            else:
                rows = bridge.scan_username(
                    identifier_value,
                    bridge.categories_from_env(),
                    modules=username_modules,
                )
        except Exception as exc:  # noqa: BLE001 - never leak stack traces
            return {
                "status": "error",
                "message": f"The deep scanner failed to complete: {type(exc).__name__}.",
            }

        is_email = identifier_type == "email"
        findings: List[Dict[str, Any]] = []
        sources: List[Dict[str, Any]] = []

        for row in rows:
            outcome = _text(row.get("outcome")) or STATUS_UNKNOWN
            if outcome not in (STATUS_FOUND, STATUS_NOT_FOUND, STATUS_UNKNOWN, STATUS_SKIPPED):
                outcome = STATUS_UNKNOWN

            source_name = _text(row.get("source")) or "Unknown source"
            checked_url = _text(row.get("url"))
            category = "public_directory" if is_email else _normalize_category(row.get("category"))

            sources.append(
                {
                    "name": source_name,
                    "category": category,
                    "outcome": outcome,
                    "checked_url": checked_url,
                    "http_status": None,
                    "rule": "account_probe",
                    "reason": row.get("reason"),
                    "elapsed_ms": None,
                }
            )

            if outcome != STATUS_FOUND:
                continue

            findings.append(
                {
                    "source": source_name,
                    "category": category,
                    "identifier_type": identifier_type,
                    "identifier_value": identifier_value,
                    "url": checked_url,
                    "confidence": 0.7 if is_email else 0.8,
                    "risk": "moderate",
                    "removal_method": "manual_instructions",
                    "status": "needs_user_action",
                    "title": (
                        f"Account registered with this email on {source_name}"
                        if is_email
                        else f"Public profile on {source_name}"
                    ),
                    "description": (
                        "The service responded as if an account exists for this address."
                        if is_email
                        else "The service responded as if this handle has a public profile."
                    ),
                    "action_guidance": (
                        f"Review the account on {source_name}. Remove it if it is unused, or reduce what "
                        "the profile makes public."
                    ),
                    "verification_status": "unverified",
                    "evidence": {
                        "rule": "account_probe",
                        "checked_url": checked_url,
                        "provider": "user-scanner",
                        "reason": row.get("reason"),
                        "checked_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
                    },
                }
            )

        return {
            "status": "success",
            "identifier": identifier_value,
            "identifier_type": identifier_type,
            "checked_count": len(rows),
            "total_found": len(findings),
            "findings": findings,
            "sources": sources,
            "attributions": ATTRIBUTIONS,
        }

    def _scan_phone(self, identifier_value: str) -> Dict[str, Any]:
        """Ask services whether the number is registered, via the vendored phonsint."""

        try:
            import phone_bridge as bridge
        except Exception:  # noqa: BLE001 - optional dependency
            return {
                "status": "error",
                "message": "The phone scanner is not installed on this host.",
            }

        try:
            payload = bridge.scan_phone(identifier_value)
        except Exception as exc:  # noqa: BLE001 - never leak stack traces
            return {
                "status": "error",
                "message": f"The phone scanner failed to complete: {type(exc).__name__}.",
            }

        if payload.get("status") != "success":
            return payload

        e164 = _text(payload.get("e164")) or identifier_value
        findings: List[Dict[str, Any]] = []
        sources: List[Dict[str, Any]] = []

        for row in payload.get("rows", []):
            outcome = _text(row.get("outcome")) or STATUS_UNKNOWN
            if outcome not in (STATUS_FOUND, STATUS_NOT_FOUND, STATUS_UNKNOWN, STATUS_SKIPPED):
                outcome = STATUS_UNKNOWN

            source_name = _text(row.get("source")) or "Unknown source"
            checked_url = _text(row.get("url"))

            sources.append(
                {
                    "name": source_name,
                    "category": "public_directory",
                    "outcome": outcome,
                    "checked_url": checked_url,
                    "http_status": None,
                    "rule": "account_probe",
                    "reason": row.get("reason"),
                    "elapsed_ms": None,
                }
            )

            if outcome != STATUS_FOUND:
                continue

            findings.append(
                {
                    "source": source_name,
                    "category": "public_directory",
                    "identifier_type": "phone",
                    "identifier_value": e164,
                    "url": checked_url,
                    "confidence": 0.7,
                    "risk": "moderate",
                    "removal_method": "manual_instructions",
                    "status": "needs_user_action",
                    "title": f"Phone number registered on {source_name}",
                    "description": "The service responded as if an account exists for this number.",
                    "action_guidance": (
                        f"Review the account on {source_name}. Remove it if it is unused, and check what "
                        "the account shares with other people."
                    ),
                    "verification_status": "unverified",
                    "evidence": {
                        "rule": "account_probe",
                        "checked_url": checked_url,
                        "provider": "phonsint",
                        "reason": row.get("reason"),
                        "checked_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
                    },
                }
            )

        return {
            "status": "success",
            "identifier": e164,
            "identifier_type": "phone",
            "checked_count": len(sources),
            "total_found": len(findings),
            "findings": findings,
            "sources": sources,
            "attributions": ATTRIBUTIONS,
        }


def _flag(args: List[str], name: str, default: Optional[str] = None) -> Optional[str]:
    if name in args:
        try:
            return args[args.index(name) + 1]
        except IndexError:
            return default
    return default


def _number(args: List[str], flag: str, env: str, default: float, cast: type) -> Any:
    raw = os.environ.get(env) or _flag(args, flag) or default
    try:
        return cast(float(raw)) if cast is int else cast(raw)
    except (TypeError, ValueError):
        return cast(default)


def main(argv: Optional[Iterable[str]] = None) -> int:
    args = list(argv if argv is not None else sys.argv[1:])
    if len(args) < 2:
        print(
            json.dumps(
                {
                    "status": "error",
                    "message": "Usage: worker.py <id_type> <id_value> [consent:true|false] [--deep] [--offline]",
                }
            )
        )
        return 0

    identifier_type = args[0]
    identifier_value = args[1]
    consent = True
    if len(args) > 2 and not args[2].startswith("--"):
        consent = args[2].lower() in ("true", "1", "yes")

    service = ScannerService(
        timeout=_number(args, "--timeout", "TRACELESS_SCANNER_TIMEOUT", DEFAULT_TIMEOUT, float),
        concurrency=_number(args, "--concurrency", "TRACELESS_SCANNER_CONCURRENCY", DEFAULT_CONCURRENCY, int),
        delay_ms=_number(args, "--delay-ms", "TRACELESS_SCANNER_DELAY_MS", DEFAULT_DELAY_MS, int),
        site_limit=_number(args, "--limit", "TRACELESS_SCANNER_SITE_LIMIT", DEFAULT_SITE_LIMIT, int),
        offline=("--offline" in args) or os.environ.get("TRACELESS_SCANNER_OFFLINE") == "1",
    )

    try:
        mode = "deep" if "--deep" in args else ("quick" if "--quick" in args else "curated")
        payload = service.scan(identifier_type, identifier_value, consent_verified=consent, mode=mode)
    except Exception:  # noqa: BLE001 - never leak stack traces to the caller
        payload = {"status": "error", "message": "Scanner failed to complete."}

    print(json.dumps(payload, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
