# TraceLess scanner overrides

`python-services/upstream/` is git-ignored and fetched fresh by CI/runtime. Any
TraceLess-owned scanner module belongs here instead.

`user_scanner_bridge.py` copies these files into the fetched upstream package
before importing it:

```
overrides/user_scanner/<path relative to upstream's user_scanner package>
```

Current overlay:

- `user_scanner/user_scan/social/nairaland.py` — public Nairaland member profiles
  (`nairaland.com/{username}`). Browser-impersonated via the upstream
  `impersonate_validate` helper; extracts only short public fields. 404 is
  `not_found`; a challenge marker-less 200 is `unknown`; never a false match.
