param(
    [Parameter(Position = 0)]
    [string]$TargetDir,

    [switch]$Force
)

function Show-Usage {
    @"
Usage:
  ./scripts/install.ps1 <target-dir> [-Force]

Install the BosskuAI workspace layer into an existing project workspace.

Installed entries:
  AGENTS.md
  CLAUDE.md
  WORKSPACE-ONBOARDING.md
  .codex/
  .claude/
  .cursor/
  ai-assistant/

Behavior:
  - Refuses to overwrite existing entries by default
  - With -Force, moves conflicting entries into a timestamped backup folder
"@
}

if (-not $TargetDir) {
    Show-Usage
    exit 1
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot = Split-Path -Parent $ScriptDir

try {
    $ResolvedTarget = (Resolve-Path $TargetDir).Path
} catch {
    Write-Error "Target directory does not exist: $TargetDir"
    exit 1
}

$Entries = @(
    "AGENTS.md",
    "CLAUDE.md",
    "WORKSPACE-ONBOARDING.md",
    ".codex",
    ".claude",
    ".cursor",
    "ai-assistant"
)

$Conflicts = @()
foreach ($Entry in $Entries) {
    if (Test-Path (Join-Path $ResolvedTarget $Entry)) {
        $Conflicts += $Entry
    }
}

if ($Conflicts.Count -gt 0 -and -not $Force) {
    Write-Host "Refusing to overwrite existing target entries:" -ForegroundColor Yellow
    foreach ($Conflict in $Conflicts) {
        Write-Host "  - $Conflict"
    }
    Write-Host ""
    Write-Host "Re-run with -Force to back up and replace those entries." -ForegroundColor Yellow
    exit 2
}

$BackupDir = $null
if ($Conflicts.Count -gt 0 -and $Force) {
    $Timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $BackupDir = Join-Path $ResolvedTarget ".bosskuai-backups\$Timestamp"
    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null

    foreach ($Conflict in $Conflicts) {
        $SourcePath = Join-Path $ResolvedTarget $Conflict
        $BackupPath = Join-Path $BackupDir $Conflict
        $BackupParent = Split-Path -Parent $BackupPath
        if ($BackupParent) {
            New-Item -ItemType Directory -Path $BackupParent -Force | Out-Null
        }
        Move-Item -Path $SourcePath -Destination $BackupPath
    }
}

foreach ($Entry in $Entries) {
    $SourcePath = Join-Path $RepoRoot $Entry
    $DestinationPath = Join-Path $ResolvedTarget $Entry
    Copy-Item -Path $SourcePath -Destination $DestinationPath -Recurse
}

Write-Host "BosskuAI workspace layer installed to: $ResolvedTarget"
if ($BackupDir) {
    Write-Host "Backed up replaced entries to: $BackupDir"
}
Write-Host "Next step: run ./scripts/check-workspace.sh `"$ResolvedTarget`""
