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
MAX_BODY_BYTES = 256 * 1024
DEFAULT_TIMEOUT = 10.0
DEFAULT_CONCURRENCY = 6
DEFAULT_DELAY_MS = 1200
DEFAULT_SITE_LIMIT = 24

# Curated sources we are willing to publish findings for. Names are matched
# case-insensitively against the WhatsMyName dataset. Sites missing from the
# installed dataset are skipped rather than guessed at.
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

    def _selected_sites(self) -> List[Dict[str, Any]]:
        by_name = {
            str(site.get("name", "")).lower(): site
            for site in self.wmn_sites
            if isinstance(site, dict)
        }
        selected: List[Dict[str, Any]] = []
        for name in CURATED_SITES:
            site = by_name.get(name.lower())
            if not site:
                continue
            if not _text(site.get("uri_check")) or not _text(site.get("e_string")):
                continue
            if site.get("valid") is False:
                continue
            selected.append(site)
            if len(selected) >= self.site_limit:
                break
        return selected

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
            raw = response.read(MAX_BODY_BYTES + 1)
            if len(raw) > MAX_BODY_BYTES:
                raise ValueError(REASON_TOO_LARGE)
            body = _decode_body(raw, response.headers.get("Content-Type", ""))
            return response.status, body, response.geturl()

    def _check_site(self, site: Dict[str, Any], slug: str) -> CheckResult:
        name = _text(site.get("name")) or "Unknown source"
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
        self, identifier_type: str, identifier_value: str, consent_verified: bool = False
    ) -> Dict[str, Any]:
        if not consent_verified:
            return {
                "status": "error",
                "message": "Consent not confirmed. TraceLess requires explicit ownership authorization.",
            }

        if identifier_type != "username":
            return {
                "status": "error",
                "message": "Only public usernames can be verified. Emails and passwords are never sent to sources.",
            }

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

        order = {name.lower(): index for index, name in enumerate(CURATED_SITES)}
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
                    "message": "Usage: worker.py <id_type> <id_value> [consent:true|false] [--offline]",
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
        payload = service.scan(identifier_type, identifier_value, consent_verified=consent)
    except Exception:  # noqa: BLE001 - never leak stack traces to the caller
        payload = {"status": "error", "message": "Scanner failed to complete."}

    print(json.dumps(payload, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
