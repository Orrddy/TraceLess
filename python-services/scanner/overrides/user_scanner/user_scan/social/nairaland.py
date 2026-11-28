"""Nairaland public profile probe.

Nairaland is Nigeria's largest public forum. Member profiles are public at
``https://www.nairaland.com/{username}`` and expose fields such as location,
gender, linked social handles, registration date and post counts. This is a
public-profile check only: no login, no private messages and no account data.

The site uses a CDN bot challenge that rejects plain ``httpx`` requests from
this host, so this module uses the repository's browser-impersonation helper.
A challenge/403 is returned as an error (``unknown`` to TraceLess), never as a
finding.
"""

from __future__ import annotations

import html
import re
import urllib.parse

from user_scanner.core.impersonate import impersonate_validate
from user_scanner.core.result import Result


def _clean(value: str) -> str:
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", value))).strip(" :-\t\r\n")


def _parse_profile(page: str) -> dict[str, str]:
    plain = _clean(page)
    # Keep only short, stable public fields. The profile page also contains long
    # follower/post lists; never copy those into evidence.
    labels = (
        "Personal text",
        "Gender",
        "YIM",
        "Location",
        "Twitter",
        "Time registered",
        "Time spent online",
        "Last seen",
    )
    stop = r"\s+(?:" + "|".join(re.escape(label) for label in labels) + r"|View |Following |Sections:|Links:)\s*"
    boundary = rf"(?={stop}|$)"
    extra: dict[str, str] = {}
    keep = {
        "Gender": "gender",
        "Location": "location",
        "Twitter": "twitter",
        "Time registered": "registered",
        "Last seen": "last_seen",
    }
    for label, key in keep.items():
        match = re.search(
            rf"{re.escape(label)}\s*:\s*(.+?){boundary}",
            plain,
            re.IGNORECASE,
        )
        if not match:
            continue
        value = match.group(1).strip(" :-\t\r\n")
        if value:
            extra[key] = value[:160]
    return extra


def validate_nairaland(user: str) -> Result:
    encoded_user = urllib.parse.quote(user, safe="")
    show_url = f"https://www.nairaland.com/{encoded_user}"

    def process(response) -> Result:
        if response.status_code == 404:
            return Result.available(url=show_url)

        if response.status_code in (401, 403, 429, 451):
            return Result.error("Nairaland blocked the profile check", url=show_url)

        if response.status_code != 200:
            return Result.error(f"Unexpected response status: {response.status_code}", url=show_url)

        page = response.text
        title_match = re.search(r"<title[^>]*>(.*?)</title>", page, re.IGNORECASE | re.DOTALL)
        canonical_name = _clean(title_match.group(1)) if title_match else ""
        canonical_name = re.sub(r"'s Profile$", "", canonical_name, flags=re.IGNORECASE).strip()

        # A real profile page always carries this field. A Cloudflare challenge
        # or any other 200 page without it is an error, not an available name.
        if "Time registered" not in page:
            return Result.error("Nairaland returned a page without a public profile", url=show_url)

        if canonical_name and canonical_name.lower() != user.lower():
            return Result.error("Nairaland redirected to a different profile", url=show_url)

        return Result.taken(url=show_url, extra=_parse_profile(page))

    return impersonate_validate(
        show_url,
        process,
        warmup_url="https://www.nairaland.com/",
        impersonate="chrome",
        allow_redirects=True,
        show_url=show_url,
    )
