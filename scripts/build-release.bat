@echo off
rem Builds release\mmts-update-YYYY-MM-DD.zip from the last commit, ready to upload to cPanel.
rem Leaves out docs, local-only tools and the sample-task CSV; never includes config.local.php,
rem databases or uploads, so no test or sample data reaches the live site.
setlocal
cd /d "%~dp0\.."

for /f %%d in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"') do set "BUILD_DATE=%%d"
if not exist release mkdir release

git archive --format=zip -o "release\mmts-update-%BUILD_DATE%.zip" HEAD -- . ^
  ":(exclude)docs" ":(exclude)scripts" ":(exclude)README.md" ":(exclude).gitignore" ^
  ":(exclude)router.php" ":(exclude)start-php-local.bat" ":(exclude)creative-requests-seed.csv"
if errorlevel 1 (
  echo Build failed.
  exit /b 1
)

echo Built release\mmts-update-%BUILD_DATE%.zip
