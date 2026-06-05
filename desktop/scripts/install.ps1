# BosskuAI native desktop bootstrap (Hermes-style).
# Provisions portable PHP, Node, Git, Composer; builds the stack; runs Laravel setup.
param(
    [Parameter(Mandatory = $true)][string]$StackDir,
    [Parameter(Mandatory = $true)][string]$BosskuHome,
    [switch]$SkipRuntimeInstall
)

$ErrorActionPreference = 'Stop'

# Windows PowerShell 5.1 defaults to TLS 1.0/1.1, which the download hosts reject — force TLS 1.2.
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
} catch {}

function Write-ProgressLine([string]$Message) {
    Write-Host "BOSSKU: $Message"
}

# Download a file, trying each URL in turn. PHP for Windows moves superseded patch
# releases from /releases/ to /releases/archives/, so a pinned version 404s once a
# newer patch ships — the archive URL is the fallback.
function Get-RemoteFile([string[]]$Urls, [string]$OutFile) {
    $lastErr = $null
    foreach ($u in $Urls) {
        try {
            Write-ProgressLine "Downloading $u"
            Invoke-WebRequest -Uri $u -OutFile $OutFile -UseBasicParsing
            if (Test-Path $OutFile) { return }
        } catch {
            $lastErr = $_
            Write-ProgressLine "Source failed ($($_.Exception.Message)); trying next..."
        }
    }
    throw "All download sources failed for $OutFile. Last error: $(if ($lastErr) { $lastErr.Exception.Message } else { 'unknown' })"
}

$RuntimeDir = Join-Path $BosskuHome 'runtime'
$BinDir = Join-Path $BosskuHome 'bin'
$DataDir = Join-Path $BosskuHome 'data'
$LogDir = Join-Path $BosskuHome 'logs'
$PhpDir = Join-Path $RuntimeDir 'php'
$NodeDir = Join-Path $RuntimeDir 'node'
$GitDir = Join-Path $RuntimeDir 'git'
$ComposerPhar = Join-Path $BinDir 'composer.phar'

$PhpVersion = '8.3.21'
$NodeVersion = '22.16.0'
$MinGitVersion = '2.49.0'

New-Item -ItemType Directory -Force -Path $RuntimeDir, $BinDir, $DataDir, $LogDir | Out-Null

function Expand-Zip([string]$ZipPath, [string]$DestDir) {
    if (Test-Path $DestDir) { Remove-Item -Recurse -Force $DestDir }
    New-Item -ItemType Directory -Force -Path $DestDir | Out-Null
    Expand-Archive -Path $ZipPath -DestinationPath $DestDir -Force
}

function Ensure-Php {
    $phpExe = Join-Path $PhpDir 'php.exe'
    if (Test-Path $phpExe) { return $phpExe }
    if ($SkipRuntimeInstall) {
        throw "php.exe not found at $phpExe (SkipRuntimeInstall)"
    }
    Write-ProgressLine "Downloading PHP $PhpVersion..."
    $zip = Join-Path $env:TEMP "bossku-php-$PhpVersion.zip"
    Get-RemoteFile @(
        "https://windows.php.net/downloads/releases/php-$PhpVersion-Win32-vs16-x64.zip",
        "https://windows.php.net/downloads/releases/archives/php-$PhpVersion-Win32-vs16-x64.zip"
    ) $zip
    Expand-Zip $zip (Join-Path $RuntimeDir 'php-extract')
    $extractRoot = Join-Path $RuntimeDir 'php-extract'
    # PHP's Windows zip extracts php.exe, php.ini-development and ext\ directly at the root
    # (no wrapper folder, unlike Node's zip). Only descend into a nested dir if php.exe
    # isn't at the root — otherwise we'd wrongly move just the ext\ subfolder.
    if (Test-Path $PhpDir) { Remove-Item -Recurse -Force $PhpDir }
    if (Test-Path (Join-Path $extractRoot 'php.exe')) {
        Move-Item -Force $extractRoot $PhpDir
    } else {
        $inner = Get-ChildItem $extractRoot -Directory | Where-Object { Test-Path (Join-Path $_.FullName 'php.exe') } | Select-Object -First 1
        if (-not $inner) { throw 'php.exe not found in extracted PHP archive' }
        Move-Item -Force $inner.FullName $PhpDir
    }
    Remove-Item -Recurse -Force $extractRoot -ErrorAction SilentlyContinue
  Remove-Item $zip -Force -ErrorAction SilentlyContinue
    # Enable extensions Laravel needs.
    $ini = Join-Path $PhpDir 'php.ini'
    if (-not (Test-Path $ini)) { Copy-Item (Join-Path $PhpDir 'php.ini-development') $ini }
    $ext = @(
        'extension_dir = "ext"',
        'extension=curl',
        'extension=fileinfo',
        'extension=mbstring',
        'extension=openssl',
        'extension=pdo_sqlite',
        'extension=sqlite3',
        'extension=zip'
    )
    Add-Content -Path $ini -Value ($ext -join "`n")
    # PHP for Windows ships no CA bundle; without one, curl/openssl reject every HTTPS cert
    # ("self-signed certificate in certificate chain") — breaking composer and the app's
    # Ollama Cloud calls. Fetch Mozilla's bundle and point php.ini at it.
    $cacert = Join-Path $PhpDir 'cacert.pem'
    Get-RemoteFile @('https://curl.se/ca/cacert.pem') $cacert
    Add-Content -Path $ini -Value ('curl.cainfo = "' + $cacert + '"')
    Add-Content -Path $ini -Value ('openssl.cafile = "' + $cacert + '"')
    return $phpExe
}

function Ensure-Node {
    $nodeExe = Join-Path $NodeDir 'node.exe'
    if (Test-Path $nodeExe) { return $nodeExe }
    if ($SkipRuntimeInstall) {
        throw "node.exe not found at $nodeExe"
    }
    Write-ProgressLine "Downloading Node.js $NodeVersion..."
    $zip = Join-Path $env:TEMP "bossku-node-$NodeVersion.zip"
    Get-RemoteFile @("https://nodejs.org/dist/v$NodeVersion/node-v$NodeVersion-win-x64.zip") $zip
    Expand-Zip $zip (Join-Path $RuntimeDir 'node-extract')
    $inner = Get-ChildItem (Join-Path $RuntimeDir 'node-extract') -Directory | Where-Object { $_.Name -like 'node-v*' } | Select-Object -First 1
    if ($inner) { Move-Item -Force $inner.FullName $NodeDir }
    Remove-Item -Recurse -Force (Join-Path $RuntimeDir 'node-extract') -ErrorAction SilentlyContinue
    Remove-Item $zip -Force -ErrorAction SilentlyContinue
    return $nodeExe
}

function Ensure-Git {
    $bash = Join-Path $GitDir 'usr\bin\bash.exe'
    if (-not (Test-Path $bash)) {
        $bash = Join-Path $GitDir 'bin\bash.exe'
    }
    if (Test-Path $bash) { return $bash }
    Write-ProgressLine "Downloading Portable Git..."
    $zip = Join-Path $env:TEMP 'bossku-mingit.zip'
    Get-RemoteFile @("https://github.com/git-for-windows/git/releases/download/v$MinGitVersion.windows.1/MinGit-$MinGitVersion-64-bit.zip") $zip
    Expand-Zip $zip $GitDir
    Remove-Item $zip -Force -ErrorAction SilentlyContinue
    if (-not (Test-Path $bash)) {
        $bash = Join-Path $GitDir 'usr\bin\bash.exe'
        if (-not (Test-Path $bash)) { $bash = Join-Path $GitDir 'bin\bash.exe' }
    }
    return $bash
}

function Ensure-Composer {
    if (-not (Test-Path $ComposerPhar)) {
        Write-ProgressLine 'Downloading Composer...'
        Get-RemoteFile @('https://getcomposer.org/download/latest-stable/composer.phar') $ComposerPhar
    }
}

$phpExe = Ensure-Php
$nodeExe = Ensure-Node
$gitBash = Ensure-Git
Ensure-Composer

$env:PATH = "$(Split-Path $phpExe);$(Split-Path $nodeExe);$env:PATH"

$AppDir = Join-Path $StackDir 'app'
$WebDir = Join-Path $StackDir 'web'
$EnvPath = Join-Path $AppDir '.env'
$EnvDesktop = Join-Path $AppDir '.env.desktop.example'
$SqlitePath = Join-Path $DataDir 'bossku.sqlite'
$UserDocs = [Environment]::GetFolderPath('MyDocuments')

if (-not (Test-Path $EnvPath)) {
    if (-not (Test-Path $EnvDesktop)) {
        throw "Missing $EnvDesktop"
    }
    Write-ProgressLine 'Creating app/.env for native desktop...'
    $content = Get-Content $EnvDesktop -Raw
    $content = $content.Replace('__BOSSKU_HOME__', $BosskuHome.Replace('\', '/'))
    $content = $content.Replace('__STACK_ROOT__', $StackDir.Replace('\', '/'))
    $content = $content.Replace('__USER_DOCUMENTS__', $UserDocs.Replace('\', '/'))
    Set-Content -Path $EnvPath -Value $content -Encoding UTF8
}

# Append git bash path for command runner visibility.
if ($gitBash -and (Test-Path $gitBash)) {
    Add-Content -Path $EnvPath -Value "BOSSKU_GIT_BASH_PATH=$($gitBash.Replace('\', '/'))"
}

New-Item -ItemType Directory -Force -Path (Split-Path $SqlitePath) | Out-Null
if (-not (Test-Path $SqlitePath)) { New-Item -ItemType File -Path $SqlitePath | Out-Null }

function Invoke-Step([string]$Label, [scriptblock]$Block) {
    Write-ProgressLine $Label
    & $Block
    if ($LASTEXITCODE -ne 0 -and $null -ne $LASTEXITCODE) {
        throw "$Label failed (exit $LASTEXITCODE)"
    }
}

Push-Location $AppDir
try {
    Write-ProgressLine 'Installing PHP dependencies (composer)'
    # Dev deps included on purpose: first-run db:seed (DatabaseServiceProvider) needs fakerphp/faker.
    # If composer.lock drifts from composer.json, install aborts ("lock not up to date");
    # fall back to `update` so a slightly-stale lock can't block the whole install.
    & $phpExe (Join-Path $BinDir 'composer.phar') install --optimize-autoloader --no-interaction --no-progress
    if ($LASTEXITCODE -ne 0) {
        Write-ProgressLine 'composer install failed (lock out of date?) - running composer update'
        & $phpExe (Join-Path $BinDir 'composer.phar') update --optimize-autoloader --no-interaction --no-progress
        if ($LASTEXITCODE -ne 0) { throw 'composer install/update failed' }
    }
    Invoke-Step 'Clearing any stale cached config' {
        # Belt-and-suspenders: drop a baked config cache so the native .env (file cache/
        # session, sync queue) wins instead of a Docker config pinned to redis.
        & $phpExe artisan config:clear
    }
    Invoke-Step 'Generating application key' {
        & $phpExe artisan key:generate --force
    }
    Invoke-Step 'Running migrations' {
        & $phpExe artisan migrate --force
    }
    Invoke-Step 'Seeding database' {
        & $phpExe artisan db:seed --force
    }
    Invoke-Step 'Importing knowledge base' {
        & $phpExe artisan bosskuai:import-knowledge --fresh
    }
}
finally {
    Pop-Location
}

Push-Location $WebDir
try {
    $env:BOSSKU_SKIP_PIXEL_OFFICE_IN_NUXT_BUILD = '1'
    # npm.cmd is a batch wrapper — invoke it directly. (Passing it to node.exe makes node
    # try to parse the .cmd as JavaScript: "Unexpected token ':'".)
    $npmCmd = Join-Path (Split-Path $nodeExe) 'npm.cmd'
    Invoke-Step 'Installing web dependencies (npm)' {
        & $npmCmd ci --no-audit --no-fund
    }
    Invoke-Step 'Building web dashboard (nuxt)' {
        & $npmCmd run build
    }
}
finally {
    Pop-Location
}

Write-ProgressLine 'Native bootstrap complete.'
