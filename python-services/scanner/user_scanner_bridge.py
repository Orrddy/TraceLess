"""Adapter around the vendored user-scanner (MIT, https://github.com/kaifcodec/user-scanner).

TraceLess uses two of its engines:

- username: checks a handle across curated categories of sites
- email: checks whether an address is registered on a set of services

The library is a CLI first, so it prints progress and result tables to stdout.
Everything here runs with stdout redirected, and only the JSON produced by
``main()`` reaches the real stdout, which keeps the worker's output machine
readable.

Nothing in this module writes to disk or stores the queried value. It is called
in-process by worker.py, so a missing dependency surfaces as a normal exception
that the worker turns into a tool-unavailable result rather than a crash.
"""

from __future__ import annotations

import contextlib
import io
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, List, Sequence

UPSTREAM = Path(__file__).resolve().parents[1] / "upstream" / "user-scanner"
OVERRIDES = Path(__file__).resolve().parent / "overrides" / "user_scanner"

DEFAULT_CATEGORIES = ("social", "dev")
# The eight of the ten quick-check sources that have an email-registration
# module in user-scanner. GitLab and Reddit are username-only.
QUICK_EMAIL_MODULES = ("facebook", "instagram", "x", "pinterest")
DEFAULT_DEEP_CONCURRENCY = 8
MAX_DEEP_CONCURRENCY = 32


def _deep_concurrency() -> int:
    """Cap the vendored engine's own fan-out.

    user-scanner defaults to a very high worker count; a shared server IP must
    stay a polite, low-volume client. One process is one scan identifier.
    """

    raw = os.environ.get("TRACELESS_DEEP_CONCURRENCY", "")
    try:
        value = int(raw) if raw else DEFAULT_DEEP_CONCURRENCY
    except (TypeError, ValueError):
        value = DEFAULT_DEEP_CONCURRENCY
    return max(1, min(MAX_DEEP_CONCURRENCY, value))


def _apply_concurrency() -> None:
    value = _deep_concurrency()
    from user_scanner.core import orchestrator
    from user_scanner.core import email_orchestrator

    orchestrator.set_concurrency(value)
    email_orchestrator.set_concurrency(value)


def _install_overrides() -> None:
    """Install TraceLess-owned modules into the fetched upstream clone.

    ``python-services/upstream`` is git-ignored and is cloned by CI/runtime, so
    source edits there disappear on deploy. Keep additions here instead and copy
    them over before importing the upstream package.
    """

    if not OVERRIDES.is_dir() or not UPSTREAM.is_dir():
        return

    for source in OVERRIDES.rglob("*.py"):
        relative = source.relative_to(OVERRIDES)
        target = UPSTREAM / "user_scanner" / relative
        try:
            target.parent.mkdir(parents=True, exist_ok=True)
            if not target.exists() or target.read_bytes() != source.read_bytes():
                target.write_bytes(source.read_bytes())
        except OSError:
            # A read-only upstream must not break the whole scanner. The normal
            # upstream modules are still importable; the override is just absent.
            continue


def _ensure_import_path() -> None:
    _install_overrides()
    if str(UPSTREAM) not in sys.path:
        sys.path.insert(0, str(UPSTREAM))


@contextlib.contextmanager
def _quiet():
    """Silence the library's terminal output without touching our own stdout."""

    with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
        yield


def _status_label(status: Any, is_email: bool) -> str:
    from user_scanner.core.result import Status

    if status == Status.TAKEN:
        return "found"
    if status == Status.AVAILABLE:
        return "not_found"
    if status == Status.SKIPPED:
        return "skipped"
    return "unknown"


_SOURCE_LABELS = {
    "facebook": "Facebook",
    "instagram": "Instagram",
    "x": "X",
    "x (twitter)": "X",
    "linkedin": "LinkedIn",
    "tiktok": "TikTok",
    "youtube": "YouTube",
    "reddit": "Reddit",
    "telegram": "Telegram",
    "snapchat": "Snapchat",
    "pinterest": "Pinterest",
    "github": "GitHub",
    "gitlab": "GitLab",
    "stackoverflow": "Stack Overflow",
    "gravatar": "Gravatar",
    "medium": "Medium",
    "deviantart": "DeviantArt",
    "spotify": "Spotify",
}


def _canonical_source(source: str) -> str:
    return _SOURCE_LABELS.get(source.strip().lower(), source)


def _to_row(result: Any) -> Dict[str, Any]:
    reason = result.reason
    if reason is not None and not isinstance(reason, str):
        reason = str(reason)

    return {
        "source": _canonical_source(result.site_name or result.category or "Unknown source"),
        "category": result.category or "",
        "outcome": _status_label(result.status, bool(result.is_email)),
        "url": result.url or "",
        "reason": (reason or "")[:200] or None,
        "extra": {
            str(key)[:40]: (value if isinstance(value, (bool, int)) else str(value)[:120])
            for key, value in (result.extra or {}).items()
        },
    }


def scan_username(
    username: str,
    categories: Sequence[str] | None = None,
    modules: Sequence[str] | None = None,
) -> List[Dict[str, Any]]:
    """Check a handle across the selected categories or named modules."""

    _ensure_import_path()

    from user_scanner.core.helpers import ScanConfig, load_categories, load_modules
    from user_scanner.core.orchestrator import run_user_category, run_user_module

    _apply_concurrency()
    configs = ScanConfig(show_all=True)

    if modules is not None:
        wanted = {name.strip().lower() for name in modules if name and name.strip()}
        selected_modules = [
            module
            for path in load_categories().values()
            for module in load_modules(path)
            if module.__name__.split(".")[-1].lower() in wanted
        ]
        with _quiet():
            results = run_user_module(selected_modules, username, configs)
        return [_to_row(result) for result in results]

    selected = [name.strip() for name in (categories or DEFAULT_CATEGORIES) if name and name.strip()]
    available = load_categories()

    rows: List[Dict[str, Any]] = []
    for name in selected:
        path = available.get(name)
        if path is None:
            continue
        with _quiet():
            results = run_user_category(path, username, configs)
        rows.extend(_to_row(result) for result in results)

    return rows


def scan_email(email: str, modules: Sequence[str] | None = None) -> List[Dict[str, Any]]:
    """Check whether an address is registered across email modules.

    ``modules`` limits the pass to specific module names, used by the free
    quick scan so it stays on the same ten public sources rather than running
    the full 220-service deep list.
    """

    _ensure_import_path()

    from user_scanner.core.email_orchestrator import run_email_full_batch, run_email_module_batch
    from user_scanner.core.helpers import ScanConfig, load_categories, load_modules

    _apply_concurrency()
    configs = ScanConfig(show_all=True)

    if modules is not None:
        wanted = {name.strip().lower() for name in modules if name and name.strip()}
        selected = [
            module
            for path in load_categories(is_email=True).values()
            for module in load_modules(path)
            if module.__name__.split(".")[-1].lower() in wanted
        ]
        with _quiet():
            results = run_email_module_batch(selected, email, configs)
    else:
        with _quiet():
            results = run_email_full_batch(email, configs)

    return [_to_row(result) for result in results]


def categories_from_env() -> List[str]:
    raw = os.environ.get("TRACELESS_DEEP_CATEGORIES", "")
    values = [part.strip() for part in raw.split(",") if part.strip()]
    return values or list(DEFAULT_CATEGORIES)


def main(argv: Iterable[str]) -> int:
    args = list(argv)
    if len(args) < 2:
        print(json.dumps({"status": "error", "message": "Usage: user_scanner_bridge.py <username|email> <value>"}))
        return 0

    kind, value = args[0], args[1]

    if kind == "username":
        rows = scan_username(value, categories_from_env())
    elif kind == "email":
        rows = scan_email(value)
    else:
        print(json.dumps({"status": "error", "message": f"Unsupported kind: {kind}"}))
        return 0

    print(json.dumps({"status": "success", "checked_count": len(rows), "rows": rows}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
