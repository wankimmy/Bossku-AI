param(
    [Parameter(Position = 0)][string]$TargetDir,
    [ValidateSet("core","dev","growth","design","full")][string]$Profile = "full",
    [switch]$Force,
    [switch]$SkipCheck,
    [switch]$PreserveMemory,
    [switch]$SkillsOnly,
    [switch]$SyncLayer,
    [switch]$WithHooks
)

if (-not $TargetDir) {
    Write-Host "Usage: .\scripts\install.ps1 <target-dir> [-Profile core|dev|growth|design|full] [-Force] [-SkipCheck] [-PreserveMemory] [-WithHooks]"
    exit 1
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot = Split-Path -Parent $ScriptDir
try { $ResolvedTarget = (Resolve-Path $TargetDir).Path } catch { Write-Error "Target directory does not exist: $TargetDir"; exit 1 }

function Copy-Path($Rel) {
    $src = Join-Path $RepoRoot $Rel
    $dst = Join-Path $ResolvedTarget $Rel
    if (-not (Test-Path $src)) { return }
    $parent = Split-Path -Parent $dst
    if ($parent) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
    if (Test-Path $dst) { Remove-Item -LiteralPath $dst -Recurse -Force }
    Copy-Item -LiteralPath $src -Destination $dst -Recurse -Force
}

function Get-ProfileSkills($Profile) {
    $core = @("bosskuai-workspace-assistant","bosskuai-project-understanding","bosskuai-search-first","bosskuai-human-output","bosskuai-token-saver","bosskuai-ratchet-loop","bosskuai-continuous-learning","bosskuai-context-limit-continuation")
    $dev = @("bosskuai-engineering-delivery","bosskuai-rigorous-code-review","bosskuai-bug-finding","bosskuai-software-architecture","bosskuai-codebase-analysis","bosskuai-code-revamp","bosskuai-coding-best-practices","bosskuai-devops-iac","bosskuai-docker","bosskuai-vps-docker-deployment","bosskuai-github-workflow","bosskuai-integration-testing","bosskuai-laravel-development","bosskuai-database-engineering","bosskuai-redis-caching-queues")
    $growth = @("bosskuai-market-analysis","bosskuai-marketing-growth","bosskuai-seo-geo","bosskuai-sales-strategy","bosskuai-launch-commercialization","bosskuai-competitor-intelligence","bosskuai-customer-discovery","bosskuai-growth-experiment","bosskuai-lead-intelligence","bosskuai-content-calendar")
    $design = @("bosskuai-ui-ux-design-to-code","bosskuai-design-systems","bosskuai-3d-web-development","bosskuai-gsap-animation","bosskuai-lenis-smooth-scroll")
    switch ($Profile) {
        "core" { return $core }
        "dev" { return $core + $dev }
        "growth" { return $core + $growth }
        "design" { return $core + $design }
        default { return (Get-ChildItem -LiteralPath (Join-Path $RepoRoot "ai-assistant\skills") -Directory | Select-Object -ExpandProperty Name) }
    }
}

$Entries = @("AGENTS.md","CLAUDE.md","WORKSPACE-ONBOARDING.md","skill-index.json","agents","mcp-configs",".codex",".claude",".cursor",".claude-plugin",".claude-plugin","ai-assistant")
if (-not $SkillsOnly -and -not $SyncLayer) {
    $conflicts = @($Entries | Where-Object { Test-Path (Join-Path $ResolvedTarget $_) })
    if ($conflicts.Count -gt 0 -and -not $Force) {
        Write-Host "Refusing to overwrite existing target entries:" -ForegroundColor Yellow
        $conflicts | ForEach-Object { Write-Host "  - $_" }
        Write-Host "Re-run with -Force to back up and replace those entries." -ForegroundColor Yellow
        exit 2
    }
    if ($conflicts.Count -gt 0 -and $Force) {
        $backup = Join-Path $ResolvedTarget (".bosskuai-backups\" + (Get-Date -Format "yyyyMMdd-HHmmss"))
        New-Item -ItemType Directory -Path $backup -Force | Out-Null
        foreach ($c in $conflicts) { Move-Item -LiteralPath (Join-Path $ResolvedTarget $c) -Destination (Join-Path $backup $c) -Force }
    }
}

$memoryStash = $null
if ($PreserveMemory -and (Test-Path (Join-Path $ResolvedTarget "ai-assistant\memory"))) {
    $memoryStash = Join-Path ([System.IO.Path]::GetTempPath()) ("bosskuai-memory-stash-" + [guid]::NewGuid().ToString())
    New-Item -ItemType Directory -Path $memoryStash -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $ResolvedTarget "ai-assistant\memory\*") -Destination $memoryStash -Recurse -Force -ErrorAction SilentlyContinue
}

if ($SkillsOnly) {
    foreach ($sub in @("ai-assistant\skills","ai-assistant\references","ai-assistant\scripts")) { Copy-Path $sub }
} else {
    foreach ($e in @("AGENTS.md","CLAUDE.md","WORKSPACE-ONBOARDING.md","skill-index.json","agents","mcp-configs",".codex",".claude",".cursor",".claude-plugin")) { Copy-Path $e }
    foreach ($sub in @("ai-assistant\memory","ai-assistant\references","ai-assistant\scripts","ai-assistant\hooks")) { Copy-Path $sub }
    $destSkills = Join-Path $ResolvedTarget "ai-assistant\skills"
    if (Test-Path $destSkills) { Remove-Item -LiteralPath $destSkills -Recurse -Force }
    New-Item -ItemType Directory -Path $destSkills -Force | Out-Null
    foreach ($s in (Get-ProfileSkills $Profile)) {
        $src = Join-Path $RepoRoot "ai-assistant\skills\$s"
        if (Test-Path $src) { Copy-Item -LiteralPath $src -Destination (Join-Path $destSkills $s) -Recurse -Force }
    }
    $settings = Join-Path $ResolvedTarget ".claude\settings.json"
    if ($WithHooks) {
        Copy-Item -LiteralPath (Join-Path $ResolvedTarget ".claude\settings.hooks.example.json") -Destination $settings -Force
    } else {
@'
{
  "bosskuai": {
    "hooks": "disabled-by-default",
    "note": "Run scripts/enable-hooks.sh or scripts/enable-hooks.ps1 to enable advisory Claude Code hooks."
  }
}
'@ | Set-Content -LiteralPath $settings -Encoding UTF8
    }
}

if ($memoryStash) {
    $memDest = Join-Path $ResolvedTarget "ai-assistant\memory"
    New-Item -ItemType Directory -Path $memDest -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $memoryStash "*") -Destination $memDest -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $memoryStash -Recurse -Force
}

Write-Host "BosskuAI installed to: $ResolvedTarget"
Write-Host "Profile: $Profile"
if ($WithHooks) { Write-Host "Hooks: enabled" } else { Write-Host "Hooks: disabled by default" }

if (-not $SkipCheck) {
    $bash = Get-Command bash -ErrorAction SilentlyContinue
    if ($bash) { & bash (Join-Path $ScriptDir "check-workspace.sh") $ResolvedTarget --profile $Profile; exit $LASTEXITCODE }
    Write-Host "Run validation from Git Bash/WSL: ./scripts/check-workspace.sh `"$ResolvedTarget`" --profile $Profile"
}
