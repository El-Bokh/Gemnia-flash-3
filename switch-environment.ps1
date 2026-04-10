param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('local', 'production')]
    [string]$Mode
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSCommandPath
$backendDir = Join-Path $root 'flash'
$frontendDir = Join-Path $root 'flash-vue'

# Active .env files
$backendEnv = Join-Path $backendDir '.env'
$frontendEnv = Join-Path $frontendDir '.env'

# Environment-specific template files
$backendLocalEnv = Join-Path $backendDir '.env.local'
$backendProductionEnv = Join-Path $backendDir '.env.production'
$frontendLocalEnv = Join-Path $frontendDir '.env.local'
$frontendProductionEnv = Join-Path $frontendDir '.env.production.local'

function Assert-FileExists {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [string]$Label
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Missing ${Label}: $Path"
    }
}

function Invoke-ArtisanCommands {
    param(
        [Parameter(Mandatory = $true)]
        [array]$CommandList,
        [Parameter(Mandatory = $true)]
        [string]$SkipMessage
    )

    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if (-not $phpCommand) {
        Write-Host $SkipMessage -ForegroundColor Yellow
        return
    }

    $artisanPath = Join-Path $backendDir 'artisan'
    Assert-FileExists -Path $artisanPath -Label 'artisan entrypoint'

    Push-Location $backendDir
    try {
        foreach ($commandArguments in $CommandList) {
            $artisanArguments = @($commandArguments)
            & $phpCommand.Source $artisanPath @artisanArguments
            if ($LASTEXITCODE -ne 0) {
                throw ('Laravel command failed: php artisan {0}' -f ($commandArguments -join ' '))
            }
        }
    }
    finally {
        Pop-Location
    }
}

function Show-CurrentMode {
    if (Test-Path -LiteralPath $backendEnv) {
        $content = Get-Content -LiteralPath $backendEnv -Raw
        if ($content -match 'APP_ENV=(\S+)') {
            Write-Host "Current backend APP_ENV: $($Matches[1])" -ForegroundColor Cyan
        }
    }
    if (Test-Path -LiteralPath $frontendEnv) {
        $content = Get-Content -LiteralPath $frontendEnv -Raw
        if ($content -match 'VITE_API_BASE_URL=(\S+)') {
            Write-Host "Current frontend API URL: $($Matches[1])" -ForegroundColor Cyan
        }
    }
}

Write-Host ''
Write-Host '========================================' -ForegroundColor Magenta
Write-Host '  Environment Switcher' -ForegroundColor Magenta
Write-Host '========================================' -ForegroundColor Magenta
Write-Host ''

Show-CurrentMode
Write-Host ''
Write-Host "Switching to: $($Mode.ToUpper())" -ForegroundColor Yellow
Write-Host ''

switch ($Mode) {
    'local' {
        # Validate local template files exist
        Assert-FileExists -Path $backendLocalEnv -Label 'backend local env (flash/.env.local)'
        Assert-FileExists -Path $frontendLocalEnv -Label 'frontend local env (flash-vue/.env.local)'

        # Save current production .env as backup if not already saved
        if (-not (Test-Path -LiteralPath $backendProductionEnv)) {
            if (Test-Path -LiteralPath $backendEnv) {
                Copy-Item -LiteralPath $backendEnv -Destination $backendProductionEnv
                Write-Host '[Backup] Saved backend .env -> .env.production' -ForegroundColor DarkGray
            }
        }
        if (-not (Test-Path -LiteralPath $frontendProductionEnv)) {
            if (Test-Path -LiteralPath $frontendEnv) {
                Copy-Item -LiteralPath $frontendEnv -Destination $frontendProductionEnv
                Write-Host '[Backup] Saved frontend .env -> .env.production.local' -ForegroundColor DarkGray
            }
        }

        # Copy local templates to active .env
        Copy-Item -LiteralPath $backendLocalEnv -Destination $backendEnv -Force
        Write-Host '[Backend]  .env.local -> .env' -ForegroundColor Green

        Copy-Item -LiteralPath $frontendLocalEnv -Destination $frontendEnv -Force
        Write-Host '[Frontend] .env.local -> .env' -ForegroundColor Green

        # Clear Laravel caches for local dev
        Invoke-ArtisanCommands -CommandList @(
            @('config:clear'),
            @('route:clear'),
            @('view:clear'),
            @('event:clear')
        ) -SkipMessage '[Warning] PHP not found in PATH. Skipped Laravel cache clear.'

        Write-Host ''
        Write-Host '----------------------------------------' -ForegroundColor Green
        Write-Host '  LOCAL DEVELOPMENT MODE ACTIVATED' -ForegroundColor Green
        Write-Host '----------------------------------------' -ForegroundColor Green
        Write-Host ''
        Write-Host '  Backend API:  http://localhost:8000/api' -ForegroundColor White
        Write-Host '  Frontend:     http://localhost:5173' -ForegroundColor White
        Write-Host '  Database:     flash (root@localhost)' -ForegroundColor White
        Write-Host ''
        Write-Host '  Start backend:   cd flash && php artisan serve --host=localhost' -ForegroundColor DarkGray
        Write-Host '  Start frontend:  cd flash-vue && npm run dev' -ForegroundColor DarkGray
        Write-Host ''
    }

    'production' {
        # Validate production template files exist
        Assert-FileExists -Path $backendProductionEnv -Label 'backend production env (flash/.env.production)'
        Assert-FileExists -Path $frontendProductionEnv -Label 'frontend production env (flash-vue/.env.production.local)'

        # Copy production templates to active .env
        Copy-Item -LiteralPath $backendProductionEnv -Destination $backendEnv -Force
        Write-Host '[Backend]  .env.production -> .env' -ForegroundColor Green

        Copy-Item -LiteralPath $frontendProductionEnv -Destination $frontendEnv -Force
        Write-Host '[Frontend] .env.production.local -> .env' -ForegroundColor Green

        # Rebuild Laravel caches for production
        Invoke-ArtisanCommands -CommandList @(
            @('config:clear'),
            @('route:clear'),
            @('view:clear'),
            @('event:clear'),
            @('package:discover', '--ansi'),
            @('config:cache'),
            @('route:cache'),
            @('view:cache')
        ) -SkipMessage '[Warning] PHP not found in PATH. Skipped Laravel cache rebuild.'

        Write-Host ''
        Write-Host '----------------------------------------' -ForegroundColor Cyan
        Write-Host '  PRODUCTION MODE ACTIVATED' -ForegroundColor Cyan
        Write-Host '----------------------------------------' -ForegroundColor Cyan
        Write-Host ''
        Write-Host '  Backend API:  https://klek.studio/api' -ForegroundColor White
        Write-Host '  Frontend:     https://klek.studio' -ForegroundColor White
        Write-Host '  Database:     gemnia_db (gemnia_user)' -ForegroundColor White
        Write-Host ''
        Write-Host '  Build frontend:  cd flash-vue && npm run build' -ForegroundColor DarkGray
        Write-Host '  Deploy:          ./deploy.sh' -ForegroundColor DarkGray
        Write-Host ''
    }
}