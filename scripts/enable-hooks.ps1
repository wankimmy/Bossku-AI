$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Src = Join-Path $Root ".claude\settings.hooks.example.json"
$Dest = Join-Path $Root ".claude\settings.json"
if (-not (Test-Path $Src)) {
    Write-Error "Missing hook example config: $Src"
    exit 1
}
Copy-Item -LiteralPath $Src -Destination $Dest -Force
Write-Host "BosskuAI advisory hooks enabled in .claude/settings.json"
