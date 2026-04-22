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
$LOCAL_DEPLOY_DIR = Join-Path $ROOT ".deploy-upload-$DEPLOY_TS"
$LOCAL_DEPLOY_PUBLIC_DIR = Join-Path $LOCAL_DEPLOY_DIR 'public'
$SSH_KEY_PATH = Join-Path $env:USERPROFILE '.ssh\id_ed25519'
$BACKEND_ENV = Join-Path $LARAVEL_DIR '.env'
$FRONTEND_ENV = Join-Path $VUE_DIR '.env'
$BACKEND_PRODUCTION_ENV = Join-Path $LARAVEL_DIR '.env.production'
$FRONTEND_PRODUCTION_ENV = Join-Path $VUE_DIR '.env.production.local'

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

function Invoke-NativeOrFail {
    param(
        [string]$Command,
        [string[]]$Arguments,
        [string]$FailureMessage
    )

    & $Command @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "${FailureMessage} (exit code $LASTEXITCODE)"
    }
}

function Invoke-SshOrFail {
    param(
        [string]$RemoteCommand,
        [string]$FailureMessage
    )

    Invoke-NativeOrFail -Command 'ssh' -Arguments @(
        '-i', $SSH_KEY_PATH,
        '-o', 'BatchMode=yes',
        '-o', 'PreferredAuthentications=publickey',
        $SERVER,
        $RemoteCommand
    ) -FailureMessage $FailureMessage
}

function Invoke-ScpOrFail {
    param(
        [string[]]$Paths,
        [string]$Destination,
        [switch]$Recursive,
        [string]$FailureMessage
    )

    $arguments = @(
        '-i', $SSH_KEY_PATH,
        '-o', 'BatchMode=yes',
        '-o', 'PreferredAuthentications=publickey'
    )

    if ($Recursive) {
        $arguments += '-r'
    }

    $arguments += $Paths
    $arguments += $Destination

    Invoke-NativeOrFail -Command 'scp' -Arguments $arguments -FailureMessage $FailureMessage
}

function Assert-FileExists {
    param([string]$Path, [string]$Label)

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Missing ${Label}: $Path"
    }
}

function Get-EnvAssignments {
    param([string]$Path)

    $assignments = @()
    if (-not (Test-Path -LiteralPath $Path)) {
        return $assignments
    }

    foreach ($line in Get-Content -LiteralPath $Path) {
        if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=') {
            $assignments += [PSCustomObject]@{ Key = $Matches[1]; Line = $line }
        }
    }

    return $assignments
}

function Write-TextFileUtf8NoBom {
    param(
        [string]$Path,
        [AllowEmptyString()]
        [AllowEmptyCollection()]
        [string[]]$Lines
    )

    $content = if ($Lines.Count -gt 0) {
        [string]::Join([Environment]::NewLine, $Lines) + [Environment]::NewLine
    } else {
        ''
    }

    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $content, $encoding)
}

function Copy-MergedEnvTemplate {
    param(
        [string]$TemplatePath,
        [string]$DestinationPath,
        [string]$SourceEnvPath,
        [string]$Label
    )

    Assert-FileExists -Path $TemplatePath -Label $Label

    $templateLines = [System.Collections.Generic.List[string]]::new()
    foreach ($line in Get-Content -LiteralPath $TemplatePath) {
        [void]$templateLines.Add([string]$line)
    }

    $templateKeys = @{}
    foreach ($assignment in Get-EnvAssignments -Path $TemplatePath) {
        $templateKeys[$assignment.Key] = $true
    }

    $missingCount = 0
    foreach ($assignment in Get-EnvAssignments -Path $SourceEnvPath) {
        if (-not $templateKeys.ContainsKey($assignment.Key)) {
            if ($missingCount -eq 0) {
                if ($templateLines.Count -gt 0 -and $templateLines[$templateLines.Count - 1] -ne '') {
                    [void]$templateLines.Add('')
                }
                [void]$templateLines.Add('# Preserved from existing .env during upload deploy')
            }

            [void]$templateLines.Add($assignment.Line)
            $templateKeys[$assignment.Key] = $true
            $missingCount++
        }
    }

    Write-TextFileUtf8NoBom -Path $DestinationPath -Lines @($templateLines.ToArray())

    if ($missingCount -gt 0) {
        Write-Host "  $Label -> $(Split-Path -Leaf $DestinationPath) (preserved $missingCount extra key(s))" -ForegroundColor Green
    } else {
        Write-Host "  $Label -> $(Split-Path -Leaf $DestinationPath)" -ForegroundColor Green
    }
}

function Ensure-ProductionEnv {
    Write-Host "`n══════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host '  Preparing production env files...' -ForegroundColor Cyan
    Write-Host '══════════════════════════════════════════' -ForegroundColor Cyan

    Copy-MergedEnvTemplate -TemplatePath $BACKEND_PRODUCTION_ENV -DestinationPath $BACKEND_ENV -SourceEnvPath $BACKEND_ENV -Label 'Backend production env'
    Copy-MergedEnvTemplate -TemplatePath $FRONTEND_PRODUCTION_ENV -DestinationPath $FRONTEND_ENV -SourceEnvPath $FRONTEND_ENV -Label 'Frontend production env'

    $backendContent = Get-Content -LiteralPath $BACKEND_ENV -Raw
    $frontendContent = Get-Content -LiteralPath $FRONTEND_ENV -Raw

    if ($backendContent -notmatch 'APP_ENV=production') {
        throw 'Backend .env is not in production mode after sync.'
    }

    if ($frontendContent -notmatch 'VITE_API_BASE_URL=https://klek.studio/api') {
        throw 'Frontend .env is not pointing at the production API after sync.'
    }
}

Ensure-ProductionEnv
Assert-FileExists -Path $SSH_KEY_PATH -Label 'SSH deploy key'

# ═══════════════════════════════════════════
#  Step 1 — Build Vue SPA
# ═══════════════════════════════════════════
Run-Or-Fail '1/5  Building Vue SPA...' {
    Push-Location $VUE_DIR
    try {
        if (Test-Path 'dist') { Remove-Item -Recurse -Force 'dist' }
        Invoke-NativeOrFail -Command 'npm' -Arguments @('run', 'build') -FailureMessage 'Vue build failed'
        if (-not (Test-Path 'dist/assets'))             { throw 'Missing dist/assets after build' }
        if (-not (Test-Path 'dist/asset-manifest.json')) { throw 'Missing dist/asset-manifest.json after build' }
    } finally { Pop-Location }
}

# ═══════════════════════════════════════════
#  Step 2 — Prepare local deploy bundle
# ═══════════════════════════════════════════
Run-Or-Fail '2/5  Preparing local deploy bundle...' {
    if (Test-Path $LOCAL_DEPLOY_DIR) {
        Remove-Item -Recurse -Force $LOCAL_DEPLOY_DIR
    }

    New-Item -ItemType Directory -Force $LOCAL_DEPLOY_PUBLIC_DIR | Out-Null

    # Copy built frontend assets into an isolated bundle so local file locks in flash/public
    # cannot break deployment preparation.
    Copy-Item -Recurse (Join-Path $VUE_DIR 'dist/assets') (Join-Path $LOCAL_DEPLOY_PUBLIC_DIR 'assets')
    New-Item -ItemType Directory -Force (Join-Path $LOCAL_DEPLOY_PUBLIC_DIR 'build') | Out-Null
    Copy-Item (Join-Path $VUE_DIR 'dist/asset-manifest.json') (Join-Path $LOCAL_DEPLOY_PUBLIC_DIR 'build/asset-manifest.json') -Force

    $iconsDir = Join-Path $VUE_DIR 'dist/icons'
    if (Test-Path $iconsDir) {
        Copy-Item -Recurse $iconsDir (Join-Path $LOCAL_DEPLOY_PUBLIC_DIR 'icons')
    }

    $frontendPublicFiles = @('manifest.json','robots.txt','sitemap.xml','favicon.ico','newlogo.png',
                             'klek-ai-mark.svg','flash-ai-mark.svg')
    foreach ($f in $frontendPublicFiles) {
        $src = Join-Path $VUE_DIR "dist/$f"
        if (Test-Path $src) { Copy-Item $src (Join-Path $LOCAL_DEPLOY_PUBLIC_DIR $f) -Force }
    }

    foreach ($f in @('.htaccess', 'index.php')) {
        $src = Join-Path $LARAVEL_DIR "public/$f"
        if (Test-Path $src) { Copy-Item $src (Join-Path $LOCAL_DEPLOY_PUBLIC_DIR $f) -Force }
    }

    # Stamp service worker
    $swSrc = Join-Path $VUE_DIR 'dist/sw.js'
    $swDst = Join-Path $LOCAL_DEPLOY_PUBLIC_DIR 'sw.js'
    if (Test-Path $swSrc) {
        $content = Get-Content $swSrc -Raw
        $content = $content -replace '__DEPLOY_TIMESTAMP__', $DEPLOY_TS
        $encoding = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($swDst, $content, $encoding)
    }

    # Write deploy metadata
    Write-TextFileUtf8NoBom -Path (Join-Path $LOCAL_DEPLOY_PUBLIC_DIR 'build/deploy-info.json') -Lines @("{`"deployedAt`":$DEPLOY_TS}")

    Write-Host "  Prepared local bundle with deploy timestamp: $DEPLOY_TS" -ForegroundColor Green
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
    $remoteFlash = "${REMOTE_ROOT}/flash"
    $remoteViews = "${REMOTE_ROOT}/flash/resources/views"
    $remoteRoutes = "${REMOTE_ROOT}/flash/routes"
    $remotePublicStage = "${REMOTE_ROOT}/flash/.deploy-public-${DEPLOY_TS}"
    $publicFiles = @('sw.js','manifest.json','robots.txt','sitemap.xml',
                     'favicon.ico','newlogo.png','klek-ai-mark.svg','flash-ai-mark.svg',
                     '.htaccess','index.php')

    # Stage public files remotely first so a failed upload does not break the live site.
    Invoke-SshOrFail -RemoteCommand "mkdir -p ${remotePub} ${remoteViews} ${remoteRoutes}; rm -rf ${remotePublicStage}; mkdir -p ${remotePublicStage}" -FailureMessage 'Failed to prepare remote staging directories'

    # Upload built public assets in one call to reduce password prompts and partial failures.
    Push-Location $LOCAL_DEPLOY_PUBLIC_DIR
    try {
        $publicUploadPaths = @('assets', 'build')
        if (Test-Path icons) {
            $publicUploadPaths += 'icons'
        }

        foreach ($f in $publicFiles) {
            if (Test-Path $f) {
                $publicUploadPaths += $f
            }
        }

        Invoke-ScpOrFail -Paths $publicUploadPaths -Destination "${SERVER}:${remotePublicStage}/" -Recursive -FailureMessage 'Failed to upload public assets'
    } finally { Pop-Location }

    $publishPublicCommands = @(
        "test -f ${remotePublicStage}/build/asset-manifest.json"
        "rm -rf ${remotePub}/assets ${remotePub}/build ${remotePub}/icons"
        "mv ${remotePublicStage}/assets ${remotePub}/assets"
        "mv ${remotePublicStage}/build ${remotePub}/build"
        "if test -d ${remotePublicStage}/icons; then mv ${remotePublicStage}/icons ${remotePub}/icons; fi"
    )

    foreach ($f in $publicFiles) {
        $publishPublicCommands += "if test -f ${remotePublicStage}/$f; then mv ${remotePublicStage}/$f ${remotePub}/$f; fi"
    }

    $publishPublicCommands += @(
        'for dir in public/assets public/build public/icons; do if test -d "$dir"; then find "$dir" -type d -exec chmod 755 {} +; find "$dir" -type f -exec chmod 644 {} +; fi; done'
        "rm -rf ${remotePublicStage}"
    )

    Invoke-SshOrFail -RemoteCommand ((@("cd ${REMOTE_ROOT}/flash") + $publishPublicCommands) -join '; ') -FailureMessage 'Failed to publish staged public assets'

    # Upload backend runtime sources so future backend changes are deployed too.
    Push-Location $LARAVEL_DIR
    try {
        $backendUploadPaths = @('app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'artisan', 'composer.json')
        if (Test-Path 'composer.lock') {
            $backendUploadPaths += 'composer.lock'
        }

        Invoke-ScpOrFail -Paths $backendUploadPaths -Destination "${SERVER}:${remoteFlash}/" -Recursive -FailureMessage 'Failed to upload backend runtime files'
    } finally { Pop-Location }

    Invoke-SshOrFail -RemoteCommand "test -f ${remotePub}/build/asset-manifest.json && test -f ${remoteViews}/spa.blade.php && test -f ${remoteRoutes}/web.php && test -f ${remoteFlash}/artisan && test -f ${remoteFlash}/config/app.php" -FailureMessage 'Remote file verification failed'

    Write-Host '  All files uploaded.' -ForegroundColor Green
}

# ═══════════════════════════════════════════
#  Step 4 — Clear & rebuild Laravel caches on server
# ═══════════════════════════════════════════
Run-Or-Fail '4/5  Clearing Laravel caches on server...' {
    $remoteCommands = @(
        "cd $REMOTE_ROOT/flash"
        'test -f public/build/asset-manifest.json'
        'test -f resources/views/spa.blade.php'
        'chmod -R 755 public/assets public/build public/icons 2>/dev/null || true'
        'rm -f bootstrap/cache/*.php'
        'if command -v composer >/dev/null 2>&1; then composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader; fi'
        'php artisan config:clear'
        'php artisan route:clear'
        'php artisan view:clear'
        'php artisan event:clear'
        'php artisan package:discover --ansi'
        'php artisan config:cache'
        'php artisan route:cache'
        'php artisan view:cache'
        'systemctl restart php*-fpm'
    )

    Invoke-SshOrFail -RemoteCommand ($remoteCommands -join '; ') -FailureMessage 'Failed to rebuild Laravel caches on the server'
}

# ═══════════════════════════════════════════
#  Step 5 — Reload Nginx
# ═══════════════════════════════════════════
Run-Or-Fail '5/5  Reloading Nginx...' {
    Invoke-SshOrFail -RemoteCommand 'systemctl reload nginx' -FailureMessage 'Failed to reload Nginx'
}

Write-Host ''
Write-Host '══════════════════════════════════════════' -ForegroundColor Green
Write-Host '  Deploy complete!' -ForegroundColor Green
Write-Host "  Timestamp: $DEPLOY_TS" -ForegroundColor Green
Write-Host '  Frontend -> https://klek.studio/' -ForegroundColor Green
Write-Host '  API      -> https://klek.studio/api' -ForegroundColor Green
Write-Host '══════════════════════════════════════════' -ForegroundColor Green
