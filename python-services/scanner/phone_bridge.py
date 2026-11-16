"""Adapter around the vendored phonsint (MIT, https://github.com/kaifcodec/phonsint).

A phone check asks services whether the number is registered. It never reads the
account, and phonsint avoids probes that would send an SMS where it can. Every
result here is still a probe, not a public page, so the worker marks findings
unverified.

The library prints banners and tables to stdout, so it runs under a stdout
redirect and only this module's JSON reaches the worker.
"""

from __future__ import annotations

import contextlib
import io
import json
import sys
from pathlib import Path
from typing import Any, Dict, List

UPSTREAM = Path(__file__).resolve().parents[1] / "upstream" / "phonsint"


def _ensure_import_path() -> None:
    if str(UPSTREAM) not in sys.path:
        sys.path.insert(0, str(UPSTREAM))


@contextlib.contextmanager
def _quiet():
    with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
        yield


def _outcome(status: Any) -> str:
    from phonsint.core.result import Status

    if status == Status.TAKEN:
        return "found"
    if status == Status.AVAILABLE:
        return "not_found"
    if status == Status.SKIPPED:
        return "skipped"
    return "unknown"


def scan_phone(raw_number: str) -> Dict[str, Any]:
    """Normalize the number, then ask each module whether it is registered."""

    _ensure_import_path()

    from phonsint.core.helpers import ScanConfig
    from phonsint.core.orchestrator import run_scan
    from phonsint.core.parser import parse_phone_number

    info = parse_phone_number(raw_number)

    if not info.is_valid:
        return {
            "status": "error",
            "message": "That phone number is not valid. Use the international format, for example +234...",
        }

    with _quiet():
        results = run_scan(info, ScanConfig(show_all=True))

    rows: List[Dict[str, Any]] = []
    for result in results:
        reason = result.reason
        if reason is not None and not isinstance(reason, str):
            reason = str(reason)

        rows.append(
            {
                "source": result.site_name or result.category or "Unknown source",
                "category": result.category or "",
                "outcome": _outcome(result.status),
                "url": result.url or "",
                "reason": (reason or "")[:200] or None,
                "extra": {
                    str(key)[:40]: (value if isinstance(value, (bool, int)) else str(value)[:80])
                    for key, value in (result.extra or {}).items()
                },
            }
        )

    return {
        "status": "success",
        "e164": info.e164,
        "region": info.region_code,
        "checked_count": len(rows),
        "rows": rows,
    }


def main(argv: List[str]) -> int:
    if not argv:
        print(json.dumps({"status": "error", "message": "Usage: phone_bridge.py <number>"}))
        return 0

    print(json.dumps(scan_phone(argv[0])))
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
