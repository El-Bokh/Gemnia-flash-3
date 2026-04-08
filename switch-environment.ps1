param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('local', 'production')]
    [string]$Mode
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSCommandPath
$backendDir = Join-Path $root 'flash'
$frontendDir = Join-Path $root 'flash-vue'
$backendEnv = Join-Path $backendDir '.env'
$frontendEnv = Join-Path $frontendDir '.env'
$backendProductionEnv = Join-Path $backendDir '.env.production'
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

function Set-EnvValue {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [string]$Key,
        [Parameter(Mandatory = $true)]
        [AllowEmptyString()]
        [string]$Value
    )

    $existingLines = @()
    if (Test-Path -LiteralPath $Path) {
        $existingLines = Get-Content -LiteralPath $Path
    }

    $pattern = '^{0}=' -f [regex]::Escape($Key)
    $updated = $false
    $result = New-Object System.Collections.Generic.List[string]

    foreach ($line in $existingLines) {
        if ($line -match $pattern) {
            if (-not $updated) {
                $result.Add(('{0}={1}' -f $Key, $Value))
                $updated = $true
            }

            continue
        }

        $result.Add($line)
    }

    if (-not $updated) {
        $result.Add(('{0}={1}' -f $Key, $Value))
    }

    [System.IO.File]::WriteAllLines($Path, $result, [System.Text.UTF8Encoding]::new($false))
}

function Ensure-ProductionBackups {
    if (-not (Test-Path -LiteralPath $backendProductionEnv)) {
        Copy-Item -LiteralPath $backendEnv -Destination $backendProductionEnv
    }

    if (-not (Test-Path -LiteralPath $frontendProductionEnv)) {
        Copy-Item -LiteralPath $frontendEnv -Destination $frontendProductionEnv
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
        Write-Host $SkipMessage
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

Assert-FileExists -Path $backendEnv -Label 'backend environment file'
Assert-FileExists -Path $frontendEnv -Label 'frontend environment file'

switch ($Mode) {
    'local' {
        Ensure-ProductionBackups

        Set-EnvValue -Path $backendEnv -Key 'APP_ENV' -Value 'local'
        Set-EnvValue -Path $backendEnv -Key 'APP_DEBUG' -Value 'true'
        Set-EnvValue -Path $backendEnv -Key 'APP_URL' -Value 'http://127.0.0.1:8000'
        Set-EnvValue -Path $backendEnv -Key 'DB_DATABASE' -Value 'flash'
        Set-EnvValue -Path $backendEnv -Key 'DB_USERNAME' -Value 'root'
        Set-EnvValue -Path $backendEnv -Key 'DB_PASSWORD' -Value ''
        Set-EnvValue -Path $backendEnv -Key 'SESSION_DOMAIN' -Value 'null'
        Set-EnvValue -Path $backendEnv -Key 'SANCTUM_STATEFUL_DOMAINS' -Value 'localhost,localhost:5173,localhost:8000,127.0.0.1,127.0.0.1:5173,127.0.0.1:8000,::1'
        Set-EnvValue -Path $backendEnv -Key 'FRONTEND_URL' -Value 'http://localhost:5173'
        Set-EnvValue -Path $backendEnv -Key 'GOOGLE_REDIRECT_URI' -Value 'http://127.0.0.1:8000/auth/google/callback'
        Set-EnvValue -Path $frontendEnv -Key 'VITE_API_BASE_URL' -Value 'http://127.0.0.1:8000/api'

        Invoke-ArtisanCommands -CommandList @(
            @('config:clear'),
            @('route:clear'),
            @('view:clear'),
            @('event:clear')
        ) -SkipMessage 'PHP was not found in PATH. Skipped Laravel local config refresh.'

        Write-Host 'Switched the project to local development mode.'
        Write-Host 'Backend API: http://127.0.0.1:8000/api'
        Write-Host 'Frontend:    http://localhost:5173'
    }

    'production' {
        Assert-FileExists -Path $backendProductionEnv -Label 'backend production backup'
        Assert-FileExists -Path $frontendProductionEnv -Label 'frontend production backup'

        Copy-Item -LiteralPath $backendProductionEnv -Destination $backendEnv -Force
        Copy-Item -LiteralPath $frontendProductionEnv -Destination $frontendEnv -Force

        Invoke-ArtisanCommands -CommandList @(
            @('config:clear'),
            @('route:clear'),
            @('view:clear'),
            @('event:clear'),
            @('package:discover', '--ansi'),
            @('config:cache'),
            @('route:cache'),
            @('view:cache')
        ) -SkipMessage 'PHP was not found in PATH. Skipped Laravel production cache refresh.'

        Write-Host 'Restored the project to production mode from the saved backups.'
    }
}