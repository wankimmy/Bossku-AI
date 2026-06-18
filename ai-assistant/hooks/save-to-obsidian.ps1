# Obsidian session logger -- universal hook for Cursor, Codex, and BosskuAI
# AI tools call this at the end of a meaningful session to log the session to Obsidian.
#
# Usage:
#   powershell -File "...\save-to-obsidian.ps1" `
#     -Prompt "what the user asked" `
#     -Project "repo-folder-name" `
#     -Tool "Cursor"          # Cursor | Codex | BosskuAI | Claude Code
#     -Summary "optional one-line summary of what was done"

param(
    [string]$Prompt  = "",
    [string]$Project = "",
    [string]$Tool    = "AI",
    [string]$Summary = ""
)

$ErrorActionPreference = "SilentlyContinue"

if (-not $Prompt) { exit 0 }

# Config — vault path resolves per-machine; override with $env:OBSIDIAN_VAULT_PATH
$vaultRoot = if ($env:OBSIDIAN_VAULT_PATH) { $env:OBSIDIAN_VAULT_PATH }
             else { Join-Path $env:USERPROFILE "Documents\Safwan\Safwan-Obsidian-Vault" }
$obsidianSessions = Join-Path $vaultRoot "Sessions"
$date        = Get-Date -Format "yyyy-MM-dd"
$time        = Get-Date -Format "HH:mm"
$sessionFile = Join-Path $obsidianSessions "$date.md"

# Project -> Obsidian wikilink
$projectMap = @{
    "festivent-app"          = "[[Projects/Festivent|Festivent]]"
    "festivent-portal"       = "[[Projects/Festivent Portal|Festivent Portal]]"
    "iman-quest"             = "[[Projects/Iman Quest|Iman Quest]]"
    "Bossku-AI"              = "[[Projects/Bossku-AI|Bossku-AI]]"
    "kawan"                  = "[[Projects/Kawan (PAIA)|Kawan]]"
    "festivent-hermes-docker"= "[[Projects/Festivent Hermes|Festivent Hermes]]"
    "putra"                  = "[[Projects/Putra|Putra]]"
    "splitlah"               = "[[Projects/Splitlah|Splitlah]]"
    "ezdisposal"             = "[[Projects/EZDisposal|EZDisposal]]"
    "meatlers"               = "[[Projects/Meatlers|Meatlers]]"
    "market-signal-ai"       = "[[Projects/Market Signal AI|Market Signal AI]]"
    "portfolio"              = "[[Projects/Portfolio|Portfolio]]"
    "builders-story"         = "[[Projects/Builders Story|Builders Story]]"
    "high-performance-restaurant-reservation-system" = "[[Projects/Restaurant Reservation System|Restaurant Reservation]]"
}
$projectLink = if ($projectMap.ContainsKey($Project)) { $projectMap[$Project] } else { $Project }

# Truncate long prompts
if ($Prompt.Length -gt 300) { $Prompt = $Prompt.Substring(0, 297) + "..." }

# Create Sessions folder and daily note if needed
if (-not (Test-Path $obsidianSessions)) {
    New-Item -ItemType Directory -Force $obsidianSessions | Out-Null
}

if (-not (Test-Path $sessionFile)) {
    $header = "# Sessions - $date"
    Set-Content $sessionFile $header -Encoding UTF8
}

# Build entry and append
$summaryLine = if ($Summary) { "`n**Summary:** $Summary" } else { "" }
$entry = "`n## $time | $Tool | $projectLink`n**Prompt:** $Prompt$summaryLine`n`n---"
$entry | Out-File -FilePath $sessionFile -Append -Encoding UTF8
exit 0
