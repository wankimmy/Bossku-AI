param(
    [Parameter(Position = 0)]
    [string]$TargetDir,

    [switch]$Force,

    [switch]$SkipCheck,

    [switch]$PreserveMemory,

    [switch]$SkillsOnly,

    [switch]$SyncLayer
)

function Show-Usage {
    @"
Usage:
  ./scripts/install.ps1 <target-dir> [-Force] [-SkipCheck] [-PreserveMemory] [-SkillsOnly] [-SyncLayer]

Install the BosskuAI workspace layer into an existing project workspace.

Installed entries (full install, default):
  AGENTS.md
  CLAUDE.md
  WORKSPACE-ONBOARDING.md
  skill-index.json
  agents/
  mcp-configs/
  .codex/
  .claude/
  .cursor/
  ai-assistant/

Switches:
  -Force
      Moves conflicting entries into a timestamped backup folder, then copies the layer.
  -PreserveMemory
      Before replacing ai-assistant/, saves ai-assistant/memory/ and restores it after install.
  -SkillsOnly
      Copies only ai-assistant/skills, references, and scripts. Does not change root files,
      tool configs, or ai-assistant/memory. (-Force is not used for this mode.)
  -SyncLayer
      Refreshes root docs, agents/, mcp-configs/, .codex/, .claude/, .cursor/, and ai-assistant/*
      except memory/. Never overwrites ai-assistant/memory/.
  -SkipCheck
      Skips check-workspace.sh unless bash is unavailable (same as before).

Behavior:
  - Refuses to overwrite existing entries by default (full install only)
  - With -Force, moves conflicting entries into a timestamped backup folder
  - Runs check-workspace.sh via bash when available unless -SkipCheck
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

if ($SkillsOnly -and $SyncLayer) {
    Write-Error "Use only one of -SkillsOnly or -SyncLayer"
    Show-Usage
    exit 1
}

if ($SkillsOnly -and $PreserveMemory) {
    Write-Host "Note: -SkillsOnly does not replace ai-assistant/memory; -PreserveMemory is redundant." -ForegroundColor DarkYellow
}

if ($SyncLayer -and $PreserveMemory) {
    Write-Host "Note: -SyncLayer never touches ai-assistant/memory; -PreserveMemory is redundant." -ForegroundColor DarkYellow
}

if ($SyncLayer) {
    $RootDocs = @(
        "AGENTS.md",
        "CLAUDE.md",
        "WORKSPACE-ONBOARDING.md",
        "skill-index.json",
    )
    foreach ($Doc in $RootDocs) {
        $SrcDoc = Join-Path $RepoRoot $Doc
        if (Test-Path $SrcDoc) {
            Copy-Item -LiteralPath $SrcDoc -Destination (Join-Path $ResolvedTarget $Doc) -Force
        }
    }

    $SyncDirs = @("agents", "mcp-configs", ".codex", ".claude", ".cursor")
    foreach ($Dir in $SyncDirs) {
        $SrcDir = Join-Path $RepoRoot $Dir
        if (-not (Test-Path $SrcDir)) {
            Write-Host "Warning: missing source in starter, skipping: $Dir" -ForegroundColor DarkYellow
            continue
        }
        $DestDir = Join-Path $ResolvedTarget $Dir
        if (Test-Path $DestDir) {
            Remove-Item -Path $DestDir -Recurse -Force
        }
        Copy-Item -Path $SrcDir -Destination $DestDir -Recurse
    }

    $AssistantDest = Join-Path $ResolvedTarget "ai-assistant"
    New-Item -ItemType Directory -Path $AssistantDest -Force | Out-Null
    Get-ChildItem -LiteralPath $AssistantDest -Force -ErrorAction SilentlyContinue | ForEach-Object {
        if ($_.Name -ne "memory") {
            Remove-Item -LiteralPath $_.FullName -Recurse -Force
        }
    }
    $SrcAssistant = Join-Path $RepoRoot "ai-assistant"
    Get-ChildItem -LiteralPath $SrcAssistant -Force | ForEach-Object {
        if ($_.Name -ne "memory") {
            $DestItem = Join-Path $AssistantDest $_.Name
            Copy-Item -LiteralPath $_.FullName -Destination $DestItem -Recurse -Force
        }
    }

    Write-Host "BosskuAI layer synced (docs + agents + tool configs + ai-assistant/* except memory) to: $ResolvedTarget"
    if (-not $SkipCheck) {
        $CheckScript = Join-Path $ScriptDir "check-workspace.sh"
        $Bash = Get-Command bash -ErrorAction SilentlyContinue
        if ($Bash) {
            Write-Host ""
            & bash $CheckScript $ResolvedTarget
            exit $LASTEXITCODE
        }
        Write-Host ""
        Write-Host "Install complete. Run validation from Git Bash or WSL:" -ForegroundColor Yellow
        Write-Host "  ./scripts/check-workspace.sh `"$ResolvedTarget`""
        exit 0
    }
    Write-Host "Skipped workspace check (-SkipCheck). Run: ./scripts/check-workspace.sh `"$ResolvedTarget`""
    exit 0
}

if ($SkillsOnly) {
    $AssistantDir = Join-Path $ResolvedTarget "ai-assistant"
    New-Item -ItemType Directory -Path $AssistantDir -Force | Out-Null
    foreach ($sub in @("skills", "references", "scripts")) {
        $SrcSub = Join-Path $RepoRoot "ai-assistant\$sub"
        $DestSub = Join-Path $AssistantDir $sub
        if (-not (Test-Path $SrcSub)) {
            Write-Error "Missing source path in starter: $SrcSub"
            exit 1
        }
        if (Test-Path $DestSub) {
            Remove-Item -Path $DestSub -Recurse -Force
        }
        Copy-Item -Path $SrcSub -Destination $DestSub -Recurse
    }
    Write-Host "BosskuAI skills layer (skills + references + scripts) installed under: $AssistantDir"
    if (-not $SkipCheck) {
        $CheckScript = Join-Path $ScriptDir "check-workspace.sh"
        $Bash = Get-Command bash -ErrorAction SilentlyContinue
        if ($Bash) {
            Write-Host ""
            & bash $CheckScript $ResolvedTarget
            exit $LASTEXITCODE
        }
        Write-Host ""
        Write-Host "Install complete. Run validation from Git Bash or WSL:" -ForegroundColor Yellow
        Write-Host "  ./scripts/check-workspace.sh `"$ResolvedTarget`""
        exit 0
    }
    Write-Host "Skipped workspace check (-SkipCheck). Run: ./scripts/check-workspace.sh `"$ResolvedTarget`""
    exit 0
}

$Entries = @(
    "AGENTS.md",
    "CLAUDE.md",
    "WORKSPACE-ONBOARDING.md",
    "skill-index.json",
    "agents",
    "mcp-configs",
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

$MemoryStash = $null
$HadMemoryToPreserve = $false
if ($PreserveMemory) {
    $MemoryPath = Join-Path $ResolvedTarget "ai-assistant\memory"
    if (Test-Path $MemoryPath) {
        $MemoryStash = Join-Path ([System.IO.Path]::GetTempPath()) ("bosskuai-memory-stash-" + [guid]::NewGuid().ToString())
        New-Item -ItemType Directory -Path $MemoryStash -Force | Out-Null
        Get-ChildItem -LiteralPath $MemoryPath -Force | ForEach-Object {
            Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $MemoryStash $_.Name) -Recurse -Force
        }
        $HadMemoryToPreserve = $true
        Write-Host "Preserved existing ai-assistant/memory into temporary stash (will restore after install)."
    }
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

if ($HadMemoryToPreserve -and $MemoryStash) {
    $DestMemory = Join-Path $ResolvedTarget "ai-assistant\memory"
    New-Item -ItemType Directory -Path $DestMemory -Force | Out-Null
    Get-ChildItem -LiteralPath $MemoryStash -Force | ForEach-Object {
        Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $DestMemory $_.Name) -Recurse -Force
    }
    Remove-Item -Path $MemoryStash -Recurse -Force
    Write-Host "Restored preserved ai-assistant/memory/ over the new layer."
}

Write-Host "BosskuAI workspace layer installed to: $ResolvedTarget"
if ($BackupDir) {
    Write-Host "Backed up replaced entries to: $BackupDir"
}

if (-not $SkipCheck) {
    $CheckScript = Join-Path $ScriptDir "check-workspace.sh"
    $Bash = Get-Command bash -ErrorAction SilentlyContinue
    if ($Bash) {
        Write-Host ""
        & bash $CheckScript $ResolvedTarget
        exit $LASTEXITCODE
    }
    Write-Host ""
    Write-Host "Install complete. Run validation from Git Bash or WSL:" -ForegroundColor Yellow
    Write-Host "  ./scripts/check-workspace.sh `"$ResolvedTarget`""
    exit 0
}

Write-Host "Skipped workspace check (-SkipCheck). Run: ./scripts/check-workspace.sh `"$ResolvedTarget`""
