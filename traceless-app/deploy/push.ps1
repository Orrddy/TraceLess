# Build a release locally and push it to weeny.cloud.
#
# Run from the repo root:
#   pwsh traceless-app/deploy/push.ps1 -AppName traceless
#
# Assets are built on this machine (no Node on the server), the Python scanner is
# copied along, and secrets (.env) are never uploaded; configuration comes from
# `weeny env`.

param(
    [string]$AppName = 'traceless',
    [string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'
$AppDir = Join-Path $Root 'traceless-app'
$Release = Join-Path $Root '.release'
$PythonDir = Join-Path $Root 'python-services'

if (-not (Test-Path $AppDir)) { throw "app directory not found: $AppDir" }

if (-not $SkipBuild) {
    Write-Host '== building front-end assets'
    Push-Location $AppDir
    try { npm run build } finally { Pop-Location }
}

if (Test-Path $Release) { Remove-Item $Release -Recurse -Force }
New-Item -ItemType Directory -Path $Release | Out-Null

Write-Host '== staging application'
# /XD /XF are relative to the source; .env must never be uploaded.
$robocopyArgs = @(
    $AppDir, $Release, '/E', '/NFL', '/NDL', '/NJH', '/NJS',
    '/XD', 'node_modules', '.git', 'vendor', 'storage\logs', 'tests', '.github',
    '/XF', '.env', '.phpunit.result.cache'
)
& robocopy @robocopyArgs | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with $LASTEXITCODE" }

Write-Host '== staging python scanner'
# The scanner is part of the runtime, not the Laravel app: curated username scans
# need the WhatsMyName data and deep scans need the vendored engines.
$pyArgs = @(
    $PythonDir, (Join-Path $Release 'python-services'), '/E', '/NFL', '/NDL', '/NJH', '/NJS',
    '/XD', '.venv', '__pycache__', '.git', '.pytest_cache'
)
& robocopy @pyArgs | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy (python) failed with $LASTEXITCODE" }

Write-Host '== normalising shell scripts to LF'
# Windows checkouts use CRLF, and bash reads that as a bad interpreter path.
foreach ($script in @('deploy\run.sh', 'deploy\install.sh', 'deploy\Caddyfile')) {
    $path = Join-Path $Release $script
    if (Test-Path $path) {
        $text = [IO.File]::ReadAllText($path) -replace "`r`n", "`n"
        [IO.File]::WriteAllText($path, $text)
    }
}

$sizeMb = [math]::Round(((Get-ChildItem $Release -Recurse -File | Measure-Object Length -Sum).Sum / 1MB), 1)
Write-Host "== staged $sizeMb MB at $Release"

Write-Host '== pushing'
Push-Location $Root
try {
    npx weeny-cloud push $Release $AppName `
        --build 'composer install --no-dev --optimize-autoloader --no-interaction' `
        --health 'curl -fsS http://127.0.0.1:8080/up' `
        -- 'bash deploy/run.sh'
    if ($LASTEXITCODE -ne 0) { throw "weeny push failed with $LASTEXITCODE" }
}
finally { Pop-Location }

Write-Host ''
Write-Host 'Pushed. Next steps:'
Write-Host "  npx weeny-cloud ssh 'bash -s' < traceless-app/deploy/install.sh   # once, first push only"
Write-Host "  npx weeny-cloud ssh 'cd ~/traceless-current && php artisan migrate --force'"
Write-Host "  npx weeny-cloud ssh 'weeny expose $AppName 8080'"
Write-Host "  npx weeny-cloud ssh 'journalctl -u weeny-$AppName -f'"
