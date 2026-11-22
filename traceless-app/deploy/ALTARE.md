# Deploying TraceLess on Altare (App/Docker Apps)

Altare's Always Free plan gives one machine of pooled resources (6 GB RAM,
150% CPU, 10 GB disk, 4 servers) and, importantly, apps stay online with **no
idle sleep** and can use a **custom domain with automatic HTTPS**.

Because PHP is not a first-class App Hosting runtime, TraceLess ships as a
**Docker image** and runs as a single container that supervises:

- nginx + PHP-FPM (Laravel)
- the queue worker (`queue:work`)
- the scheduler (`schedule:work`)
- the Python scanners (curated WhatsMyName, deep username/email/phone)

## 1. Build the image

The image is built by GitHub Actions on every push to `main`
(`.github/workflows/ci.yml` at the repository root) and pushed to GHCR:

```
ghcr.io/<owner>/traceless:latest
ghcr.io/<owner>/traceless:<commit-sha>
```

The workflow clones the scanner datasets (WhatsMyName, user-scanner, phonsint)
into `python-services/upstream` before building, because they are not committed
to the repository.

Confirm the "Container image" job is green in the repository's Actions tab.

## 2. Create the app in Altare

Console → Apps → Create app → **Docker image**.

| Setting | Value |
| --- | --- |
| Image | `ghcr.io/<owner>/traceless:<sha>` (pin the sha, not `latest`) |
| Registry login | GitHub username + a **PAT with `read:packages`** (private package) |
| Port | `8080` |
| Data folder | `/data` |
| Region | Frankfurt or London (lowest latency to Nigeria) |
| Memory / CPU | 2048 MB / 100% (fits the free pool together with headroom) |
| Start command | leave default (`supervisord -c /etc/supervisord-all.conf`) |

## 3. Environment variables

Set these on the app (secrets are hidden after saving):

```dotenv
APP_NAME=TraceLess
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                      # php artisan key:generate --show
APP_URL=https://traceless.orrddy.store
ASSET_URL=https://traceless.orrddy.store
LOG_CHANNEL=stderr
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_CONNECTION=pgsql
DB_URL=postgresql://...                 # Neon DIRECT endpoint, not -pooler
DB_SSLMODE=require
TRUSTED_PROXIES=*
TRACELESS_DEMO_MODE=false
TRACELESS_OTP_DEMO_DISPLAY=false
TRACELESS_PYTHON_BINARY=/opt/traceless/venv/bin/python
TRACELESS_SCANNER_SCRIPT=/var/www/html/python-services/scanner/worker.py
MAIL_MAILER=smtp
MAIL_HOST=server3.nairahost.ng
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=traceless@orrddy.store
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=traceless@orrddy.store
MAIL_FROM_NAME=TraceLess
RUN_MIGRATIONS=true
```

Notes:

- Keep the database on **Neon** for the beta. Altare's free Postgres is 256 MB
  RAM / 1 GB storage, which is less headroom than Neon.
- Mail runs through the existing DirectAdmin mailbox. Verify that outbound 587
  works from the container; if the platform blocks SMTP, switch to Resend over
  HTTPS (verify the domain at resend.com/domains first).
- `TRUSTED_PROXIES=*` is required: Altare terminates TLS in front of the
  container.

## 4. Custom domain

1. Add the app's default address (`<app>.go.altare.global`) as the target.
2. In the DNS for `orrddy.store` (currently Vercel DNS) add:

| Type | Name | Value |
| --- | --- | --- |
| `CNAME` | `traceless` | `<app>.go.altare.global` |

3. Wait for the certificate, then check `https://traceless.orrddy.store/up`.

## 5. Verify before switching production

- [ ] `/up` returns `ok` over HTTPS
- [ ] Sign-in OTP arrives and completes
- [ ] A snapshot scan completes with source telemetry
- [ ] A deep scan reaches the Python venv (no "deep scanner is not installed")
- [ ] `/admin` loads as the promoted account; queue heartbeat is fresh
- [ ] `/admin/failed-jobs` is empty
- [ ] Report download works (storage on `/data` survives a redeploy)

Keep the current weeny deployment running until every box is ticked.

## 6. Redeploys and rollback

- Push to `main` → CI builds a new sha-tagged image.
- In Altare, deploy the new image tag (or pin an older sha) — `/data` persists.
- Rollback = deploy the previous sha tag again. Migrations are additive.

## Known limits and risks

- **Free plan**: 4 servers, 10 GB disk total, no email product, cron minimum
  5 minutes (irrelevant: the scheduler runs inside the container).
- **Image size**: the image carries PHP, Python, and ~9 MB of datasets. With
  several revisions on disk, the 10 GB free disk needs watching.
- **Platform youth**: Altare is a new provider. The same image runs on any
  Docker host, so leaving is a redeploy, not a rewrite.
- **1.5 shared CPU**: deep scans take longer here than on a dedicated VPS.

## Security checklist

- Rotate the team's global SFTP password (it was shared in chat).
- The GHCR token should be a PAT with only `read:packages`.
- Never commit `.env`; set secrets only in the provider dashboards.

## Attribution

The image bundles [WhatsMyName](https://github.com/WebBreacher/WhatsMyName)
(CC BY-SA 4.0) and the MIT-licensed
[user-scanner](https://github.com/kaifcodec/user-scanner) and
[phonsint](https://github.com/kaifcodec/phonsint). Their licence files are
included in the image under `python-services/upstream/`.
