#!/usr/bin/env pwsh
# ─────────────────────────────────────────────
#  Upload built frontend + backend changes to production server
# ─────────────────────────────────────────────
#  Usage: .\upload-to-server.ps1
#  Run from the project root (parent of flash/ and flash-vue/)
#
#  What it does:
#    1. Builds the Vue SPA locally
#    2. Syncs build output into flash/public/ (same as deploy.sh)
#    3. Uploads the correct files to the server via SCP
#    4. Runs cache-clear commands on the server via SSH
# ─────────────────────────────────────────────

$ErrorActionPreference = 'Stop'

# ── Config ──
$SERVER      = 'root@72.61.192.23'
$REMOTE_ROOT = '/var/www/Gemnia-flash-3'
$ROOT        = Split-Path -Parent $PSCommandPath
$VUE_DIR     = Join-Path $ROOT 'flash-vue'
$LARAVEL_DIR = Join-Path $ROOT 'flash'
$DEPLOY_TS   = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()

# ── Helper ──
function Run-Or-Fail {
    param([string]$Label, [scriptblock]$Block)
    Write-Host "`n══════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host "  $Label" -ForegroundColor Cyan
    Write-Host "══════════════════════════════════════════" -ForegroundColor Cyan
    & $Block
    if ($LASTEXITCODE -and $LASTEXITCODE -ne 0) {
        throw "FAILED: $Label (exit code $LASTEXITCODE)"
    }
}

# ═══════════════════════════════════════════
#  Step 1 — Build Vue SPA
# ═══════════════════════════════════════════
Run-Or-Fail '1/5  Building Vue SPA...' {
    Push-Location $VUE_DIR
    try {
        if (Test-Path 'dist') { Remove-Item -Recurse -Force 'dist' }
        npm run build
        if (-not (Test-Path 'dist/assets'))             { throw 'Missing dist/assets after build' }
        if (-not (Test-Path 'dist/asset-manifest.json')) { throw 'Missing dist/asset-manifest.json after build' }
    } finally { Pop-Location }
}

# ═══════════════════════════════════════════
#  Step 2 — Sync build into flash/public/
# ═══════════════════════════════════════════
Run-Or-Fail '2/5  Syncing build into Laravel public/...' {
    # Remove old assets
    @('public/assets', 'public/build', 'public/icons') | ForEach-Object {
        $p = Join-Path $LARAVEL_DIR $_
        if (Test-Path $p) { Remove-Item -Recurse -Force $p }
    }

    # Copy new assets
    Copy-Item -Recurse (Join-Path $VUE_DIR 'dist/assets') (Join-Path $LARAVEL_DIR 'public/assets')
    New-Item -ItemType Directory -Force (Join-Path $LARAVEL_DIR 'public/build') | Out-Null
    Copy-Item (Join-Path $VUE_DIR 'dist/asset-manifest.json') (Join-Path $LARAVEL_DIR 'public/build/asset-manifest.json') -Force

    $iconsDir = Join-Path $VUE_DIR 'dist/icons'
    if (Test-Path $iconsDir) {
        Copy-Item -Recurse $iconsDir (Join-Path $LARAVEL_DIR 'public/icons')
    }

    # Copy individual public files
    $publicFiles = @('manifest.json','robots.txt','sitemap.xml','favicon.ico','newlogo.png',
                     'klek-ai-mark.svg','flash-ai-mark.svg')
    foreach ($f in $publicFiles) {
        $src = Join-Path $VUE_DIR "dist/$f"
        if (Test-Path $src) { Copy-Item $src (Join-Path $LARAVEL_DIR "public/$f") -Force }
    }

    # Stamp service worker
    $swSrc = Join-Path $VUE_DIR 'dist/sw.js'
    $swDst = Join-Path $LARAVEL_DIR 'public/sw.js'
    if (Test-Path $swSrc) {
        $content = Get-Content $swSrc -Raw
        $content = $content -replace '__DEPLOY_TIMESTAMP__', $DEPLOY_TS
        Set-Content -Path $swDst -Value $content -NoNewline
    }

    # Write deploy metadata
    Set-Content -Path (Join-Path $LARAVEL_DIR 'public/build/deploy-info.json') -Value "{`"deployedAt`":$DEPLOY_TS}"

    Write-Host "  Synced with deploy timestamp: $DEPLOY_TS" -ForegroundColor Green
}

# ═══════════════════════════════════════════
#  Step 3 — Upload to server
# ═══════════════════════════════════════════
#  NOTE: We Push-Location into directories so SCP only sees
#  relative paths (no spaces). This avoids the PS 5.1 bug
#  where native-command arguments with spaces aren't quoted.
# ═══════════════════════════════════════════
Run-Or-Fail '3/5  Uploading files to server...' {
    $remotePub = "${REMOTE_ROOT}/flash/public"

    # Remove old assets on server first
    ssh $SERVER "rm -rf ${remotePub}/assets ${remotePub}/build ${remotePub}/icons"

    # Upload directories (assets, build, icons) using relative paths
    Push-Location (Join-Path $LARAVEL_DIR 'public')
    try {
        scp -r assets  "${SERVER}:${remotePub}/"
        scp -r build   "${SERVER}:${remotePub}/"
        if (Test-Path icons) {
            scp -r icons "${SERVER}:${remotePub}/"
        }

        # Upload individual public files
        $uploadFiles = @('sw.js','manifest.json','robots.txt','sitemap.xml',
                         'newlogo.png','.htaccess','index.php')
        foreach ($f in $uploadFiles) {
            if (Test-Path $f) {
                scp $f "${SERVER}:${remotePub}/$f"
            }
        }
    } finally { Pop-Location }

    # Upload the Blade template + routes
    Push-Location $LARAVEL_DIR
    try {
        scp "resources/views/spa.blade.php" "${SERVER}:${REMOTE_ROOT}/flash/resources/views/spa.blade.php"
        scp "routes/web.php"                "${SERVER}:${REMOTE_ROOT}/flash/routes/web.php"
    } finally { Pop-Location }

    Write-Host '  All files uploaded.' -ForegroundColor Green
}

# ═══════════════════════════════════════════
#  Step 4 — Clear & rebuild Laravel caches on server
# ═══════════════════════════════════════════
Run-Or-Fail '4/5  Clearing Laravel caches on server...' {
    ssh $SERVER @"
cd $REMOTE_ROOT/flash
chmod -R 755 public/assets public/build public/icons 2>/dev/null || true
rm -f bootstrap/cache/*.php
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
systemctl restart php*-fpm
"@
}

# ═══════════════════════════════════════════
#  Step 5 — Reload Nginx
# ═══════════════════════════════════════════
Run-Or-Fail '5/5  Reloading Nginx...' {
    ssh $SERVER 'systemctl reload nginx'
}

Write-Host ''
Write-Host '══════════════════════════════════════════' -ForegroundColor Green
Write-Host '  Deploy complete!' -ForegroundColor Green
Write-Host "  Timestamp: $DEPLOY_TS" -ForegroundColor Green
Write-Host '  Frontend -> https://klek.studio/' -ForegroundColor Green
Write-Host '  API      -> https://klek.studio/api' -ForegroundColor Green
Write-Host '══════════════════════════════════════════' -ForegroundColor Green
