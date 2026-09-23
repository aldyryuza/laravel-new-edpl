param(
    [Parameter(Mandatory = $true)]
    [string]$TargetPath
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path -LiteralPath $TargetPath -PathType Container)) {
    throw "Target directory does not exist: $TargetPath"
}

$TemplateRoot = Split-Path -Parent $PSScriptRoot

$Directories = @(
    (Join-Path $TargetPath "docs"),
    (Join-Path $TargetPath ".github"),
    (Join-Path $TargetPath "app/Services"),
    (Join-Path $TargetPath "app/Actions"),
    (Join-Path $TargetPath "app/Repositories")
)

foreach ($Directory in $Directories) {
    New-Item -ItemType Directory -Force -Path $Directory | Out-Null
}

Copy-Item -LiteralPath (Join-Path $TemplateRoot "README.md") -Destination (Join-Path $TargetPath "README.md") -Force
Copy-Item -LiteralPath (Join-Path $TemplateRoot "AGENTS.md") -Destination (Join-Path $TargetPath "AGENTS.md") -Force
Copy-Item -LiteralPath (Join-Path $TemplateRoot "CLAUDE.md") -Destination (Join-Path $TargetPath "CLAUDE.md") -Force
Copy-Item -Path (Join-Path $TemplateRoot "docs/*.md") -Destination (Join-Path $TargetPath "docs") -Force
Copy-Item -LiteralPath (Join-Path $TemplateRoot ".github/copilot-instructions.md") -Destination (Join-Path $TargetPath ".github/copilot-instructions.md") -Force
Copy-Item -LiteralPath (Join-Path $TemplateRoot ".github/pull_request_template.md") -Destination (Join-Path $TargetPath ".github/pull_request_template.md") -Force

New-Item -ItemType File -Force -Path (Join-Path $TargetPath "app/Services/.gitkeep") | Out-Null
New-Item -ItemType File -Force -Path (Join-Path $TargetPath "app/Actions/.gitkeep") | Out-Null
New-Item -ItemType File -Force -Path (Join-Path $TargetPath "app/Repositories/.gitkeep") | Out-Null

Write-Host "Template copied to: $TargetPath"
Write-Host "Next: replace placeholders in docs/*.md and review AI instruction files."
