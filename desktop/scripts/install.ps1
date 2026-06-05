# BosskuAI native desktop bootstrap (Hermes-style).
# Provisions portable PHP, Node, Git, Composer; builds the stack; runs Laravel setup.
param(
    [Parameter(Mandatory = $true)][string]$StackDir,
    [Parameter(Mandatory = $true)][string]$BosskuHome,
    [switch]$SkipRuntimeInstall
)

$ErrorActionPreference = 'Stop'

function Write-ProgressLine([string]$Message) {
    Write-Host "BOSSKU: $Message"
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
    $url = "https://windows.php.net/downloads/releases/php-$PhpVersion-Win32-vs16-x64.zip"
    Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
    Expand-Zip $zip (Join-Path $RuntimeDir 'php-extract')
    $extracted = Get-ChildItem (Join-Path $RuntimeDir 'php-extract') -Directory | Select-Object -First 1
    if ($extracted) {
        Move-Item -Force $extracted.FullName $PhpDir
    } else {
        Move-Item -Force (Join-Path $RuntimeDir 'php-extract\*') $PhpDir
    }
    Remove-Item -Recurse -Force (Join-Path $RuntimeDir 'php-extract') -ErrorAction SilentlyContinue
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
    $url = "https://nodejs.org/dist/v$NodeVersion/node-v$NodeVersion-win-x64.zip"
    Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
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
    $url = "https://github.com/git-for-windows/git/releases/download/v$MinGitVersion.windows.1/MinGit-$MinGitVersion-64-bit.zip"
    Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
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
        Invoke-WebRequest -Uri 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile $ComposerPhar -UseBasicParsing
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
    Invoke-Step 'Installing PHP dependencies (composer)' {
        & $phpExe (Join-Path $BinDir 'composer.phar') install --no-dev --optimize-autoloader --no-interaction --no-progress
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
    Invoke-Step 'Installing web dependencies (npm)' {
        & $nodeExe (Join-Path (Split-Path $nodeExe) 'npm.cmd') ci --no-audit --no-fund
    }
    Invoke-Step 'Building web dashboard (nuxt)' {
        & $nodeExe (Join-Path (Split-Path $nodeExe) 'npm.cmd') run build
    }
}
finally {
    Pop-Location
}

Write-ProgressLine 'Native bootstrap complete.'
