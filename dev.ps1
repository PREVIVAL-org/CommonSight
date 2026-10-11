<#
  Local environment of CommonSight on Windows (doc/DEVELOPMENT.md), the PowerShell counterpart of dev.sh. Needs Docker
  Desktop and Git for Windows; the file steps run in the tool container "shell" (tools/dev/), the same scripts as dev.sh.

    .\dev.ps1 up [port]      build and start the environment (port remembered in .env, default 8090)
    .\dev.ps1 down           stop it
    .\dev.ps1 build          build the installation package (build\commonsight-<release>.tar.gz)
    .\dev.ps1 deploy         build the package and install it as a new release in the local environment
    .\dev.ps1 update-data    fetch all layers now
    .\dev.ps1 update-tiles   build the tile archive now
    .\dev.ps1 status         state of all layers
    .\dev.ps1 test           all checks and tests
    .\dev.ps1 logs           follow the logs of the services
#>
param(
    [Parameter(Position = 0)][string]$Command = '',
    [Parameter(Position = 1)][string]$Argument = ''
)

Set-StrictMode -Version 3
$ErrorActionPreference = 'Stop'

$Root = $PSScriptRoot
# Paths inside the containers.
$HomeIn = '/home/commonsight'
$Fetcher = "$HomeIn/commonsight/current/bin/fetcher.php"

# User and group of the containers; Docker Desktop maps the files in run\ to the Windows user anyway.
if (-not $env:CS_UID) { $env:CS_UID = '1000' }
if (-not $env:CS_GID) { $env:CS_GID = '1000' }

function Write-Step([string]$Text) { Write-Host "-> $Text" }

function Stop-WithError([string]$Text) {
    Write-Host "x $Text" -ForegroundColor Red
    exit 1
}

# Port of the web server: from .env (written by "up <port>"), otherwise 8090.
function Get-Port {
    $file = Join-Path $Root '.env'
    if (Test-Path $file) {
        foreach ($line in Get-Content $file) {
            if ($line -match '^CS_HTTP_PORT=(\d+)$') { return $Matches[1] }
        }
    }
    return '8090'
}

# The wrappers have no param block, so options such as -T reach docker unchanged. Their output goes to the console:
# none of them returns a value.

# docker compose; the caller checks $LASTEXITCODE.
function Invoke-ComposeUnchecked {
    & docker compose --project-directory $Root -f (Join-Path $Root 'compose.yaml') @args
}

# docker compose that stops the script when it fails.
function Invoke-Compose {
    & docker compose --project-directory $Root -f (Join-Path $Root 'compose.yaml') @args
    if ($LASTEXITCODE -ne 0) { Stop-WithError "docker compose $($args -join ' ') failed (exit code $LASTEXITCODE)" }
}

function Invoke-Tool { Invoke-Compose run --rm -T @args }

# A script of tools/dev/ (or another Bash script of the repository) in the tool container.
function Invoke-Shell { Invoke-Tool shell @args }

function Invoke-AsAccount { Invoke-Compose exec -T -u commonsight @args }

# The commit in the release name only if the package is exactly that commit; with local changes it says so ("-dirty").
function Get-Commit {
    $previous = $ErrorActionPreference
    # Windows PowerShell turns the error output of a program into an exception otherwise.
    $ErrorActionPreference = 'Continue'
    try {
        $commit = & git -C $Root rev-parse --short HEAD 2>$null
        if ($LASTEXITCODE -ne 0 -or -not $commit) { return 'nogit' }
        $changes = & git -C $Root status --porcelain 2>$null
        if ($changes) { return "$commit-dirty" }
        return "$commit"
    } catch {
        return 'nogit'
    } finally {
        $ErrorActionPreference = $previous
    }
}

function Invoke-Up {
    if ($Argument -ne '') {
        if ($Argument -notmatch '^\d+$') { Stop-WithError "Port must be a number: $Argument" }
        # UTF-8 without BOM: Compose reads .env as it is.
        [IO.File]::WriteAllText((Join-Path $Root '.env'), "CS_HTTP_PORT=$Argument`n")
    }
    Invoke-Compose build shell
    Invoke-Shell tools/dev/install.sh account
    Invoke-Compose up -d --build
    Write-Step "Running: http://localhost:$(Get-Port)/ (deploy first: .\dev.ps1 deploy)"
}

function Invoke-Down { Invoke-Compose down }

function Build-Backend {
    Write-Step 'Backend: dependencies, master data'
    Invoke-Tool composer install --no-interaction --no-progress --quiet
    Invoke-Tool composer build-contract | Out-Null
}

# npm ci when the dependencies are missing or the lockfile changed since they were installed.
function Install-FrontendDependencies {
    $lock = Join-Path $Root 'frontend/package-lock.json'
    $installed = Join-Path $Root 'frontend/node_modules/.package-lock.json'
    if (-not (Test-Path $installed) -or (Get-Item -Force $lock).LastWriteTimeUtc -gt (Get-Item -Force $installed).LastWriteTimeUtc) {
        Invoke-Tool node npm ci --no-audit --no-fund --loglevel=error
    }
}

function Build-Frontend {
    Write-Step 'Frontend: Build'
    Install-FrontendDependencies
    Invoke-Tool node npm run build | Out-Null
}

# Installation package: app/ (to <APP>/releases/<release>/), web/ (to the webroot), templates for configuration and
# cron. Sets $script:Release.
function Invoke-Build {
    $release = '{0}-{1}' -f (Get-Date).ToUniversalTime().ToString('yyyyMMddHHmmss'), (Get-Commit)
    $script:Release = $release
    Build-Backend
    Build-Frontend
    if (-not (Test-Path (Join-Path $Root 'map-assets/glyphs'))) {
        Write-Step 'Downloading the glyphs of the map'
        Invoke-Shell tools/tiles/fetch-map-assets.sh
    }
    Write-Step "Assembling package $release"
    Invoke-Shell tools/dev/package.sh assemble $release
    Invoke-Tool -w "/src/build/commonsight-$release/app" composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
    Invoke-Shell tools/dev/package.sh seal $release
    Write-Step "Package: build\commonsight-$release.tar.gz"
}

function Invoke-Deploy {
    Invoke-Shell tools/dev/install.sh account
    Invoke-Build

    Write-Step "Installing $script:Release"
    Invoke-Shell tools/dev/install.sh release $script:Release
    Invoke-Compose exec -T php sh -c 'kill -USR2 1'
    Invoke-ComposeUnchecked exec -T -u commonsight scheduler php $Fetcher check
    if ($LASTEXITCODE -ne 0) { Stop-WithError 'check reports problems' }
    Write-Step "Done: http://localhost:$(Get-Port)/"
}

function Invoke-Test {
    Write-Step 'Backend'
    Invoke-Tool composer install --no-interaction --no-progress --quiet
    Invoke-Tool composer check-all
    Write-Step 'Frontend'
    Install-FrontendDependencies
    Invoke-Tool node npm run check
    Invoke-Tool node npm run format:check
    Write-Step 'End-to-end'
    Invoke-Tool playwright npx playwright test
}

function Show-Usage {
    $lines = Get-Content $PSCommandPath -TotalCount 13 | Select-Object -Skip 4
    $lines | ForEach-Object { Write-Host $_.Trim() }
    exit 1
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Stop-WithError 'docker not found: install Docker Desktop and start it (doc/DEVELOPMENT.md, Windows)'
}

switch ($Command) {
    'up' { Invoke-Up }
    'down' { Invoke-Down }
    'build' { Invoke-Build }
    'deploy' { Invoke-Deploy }
    'update-data' { Invoke-AsAccount scheduler php -d memory_limit=256M $Fetcher run --all }
    'update-tiles' { Invoke-AsAccount scheduler "$HomeIn/commonsight/current/bin/update-tiles.sh" }
    'status' { Invoke-AsAccount scheduler php $Fetcher status }
    'test' { Invoke-Test }
    'logs' { Invoke-Compose logs -f }
    default { Show-Usage }
}
