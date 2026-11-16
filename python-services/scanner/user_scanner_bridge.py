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

DEFAULT_CATEGORIES = ("social", "dev")


def _ensure_import_path() -> None:
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


def _to_row(result: Any) -> Dict[str, Any]:
    reason = result.reason
    if reason is not None and not isinstance(reason, str):
        reason = str(reason)

    return {
        "source": result.site_name or result.category or "Unknown source",
        "category": result.category or "",
        "outcome": _status_label(result.status, bool(result.is_email)),
        "url": result.url or "",
        "reason": (reason or "")[:200] or None,
        "extra": {
            str(key)[:40]: (value if isinstance(value, (bool, int)) else str(value)[:120])
            for key, value in (result.extra or {}).items()
        },
    }


def scan_username(username: str, categories: Sequence[str] | None = None) -> List[Dict[str, Any]]:
    """Check a handle across the selected categories."""

    _ensure_import_path()

    from user_scanner.core.helpers import ScanConfig, load_categories
    from user_scanner.core.orchestrator import run_user_category

    selected = [name.strip() for name in (categories or DEFAULT_CATEGORIES) if name and name.strip()]
    available = load_categories()
    configs = ScanConfig(show_all=True)

    rows: List[Dict[str, Any]] = []
    for name in selected:
        path = available.get(name)
        if path is None:
            continue
        with _quiet():
            results = run_user_category(path, username, configs)
        rows.extend(_to_row(result) for result in results)

    return rows


def scan_email(email: str) -> List[Dict[str, Any]]:
    """Check whether an address is registered across the email modules."""

    _ensure_import_path()

    from user_scanner.core.email_orchestrator import run_email_full_batch
    from user_scanner.core.helpers import ScanConfig

    with _quiet():
        results = run_email_full_batch(email, ScanConfig(show_all=True))

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
