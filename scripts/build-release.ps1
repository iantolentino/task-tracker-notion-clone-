# Builds the cPanel upload package: only the website files that changed since the version
# that is live (git tag "live"), taken from the last commit.
#
# GitHub keeps the full system; the upload never contains repo-only files (docs, scripts,
# README, the MySQL schema), sample data, config.local.php, databases or uploaded files.
#
# After uploading, move the tag so the next package starts from the new live version:
#   git tag -f -a live -m "Live on <date>" HEAD

$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')

$repoOnly = @(
    ':(exclude)docs', ':(exclude)scripts', ':(exclude)README.md', ':(exclude).gitignore',
    ':(exclude)schema.sql', ':(exclude)router.php', ':(exclude)start-php-local.bat',
    ':(exclude)creative-requests-seed.csv', ':(exclude)config.local.php',
    ':(exclude)database/*.sqlite', ':(exclude)database/*.db'
)

git rev-parse --verify --quiet live | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'No "live" tag. Tag the commit that is running on the server first.' }

$changed = @(git diff --name-only --diff-filter=ACMR live HEAD -- . @repoOnly)
$removed = @(git diff --name-only --diff-filter=D live HEAD -- . @repoOnly)
if ($changed.Count -eq 0) { Write-Host 'Nothing changed since the live version.'; exit 0 }

$date = Get-Date -Format 'yyyy-MM-dd'
New-Item -ItemType Directory -Force -Path release | Out-Null
$zip = "release\mmts-update-$date.zip"
git archive --format=zip -o $zip HEAD -- @changed

$notes = @("MMTS update $date", '', "Upload and extract in public_html/tickets/creative/ (overwrite):")
$notes += $changed | ForEach-Object { "  $_" }
if ($removed.Count -gt 0) {
    $notes += '', 'No longer used; safe to delete from the server if present:'
    $notes += $removed | ForEach-Object { "  $_" }
}
$notes | Set-Content -Encoding utf8 "release\mmts-update-$date.txt"

Write-Host "Built $zip ($($changed.Count) files). File list: release\mmts-update-$date.txt"
