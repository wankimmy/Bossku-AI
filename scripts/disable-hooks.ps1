$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Dest = Join-Path $Root ".claude\settings.json"
@'
{
  "bosskuai": {
    "hooks": "disabled-by-default",
    "note": "Run scripts/enable-hooks.sh or scripts/enable-hooks.ps1 to enable advisory Claude Code hooks with auto memory capture."
  }
}
'@ | Set-Content -LiteralPath $Dest -Encoding UTF8
Write-Host "BosskuAI advisory hooks disabled in .claude/settings.json"
