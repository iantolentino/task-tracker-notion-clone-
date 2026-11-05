@echo off
rem Builds release\mmts-update-YYYY-MM-DD.zip from the last commit, ready to upload to cPanel.
rem Leaves out docs and local-only tools; never includes config.local.php, databases or uploads.
setlocal
cd /d "%~dp0\.."

for /f %%d in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"') do set "BUILD_DATE=%%d"
if not exist release mkdir release

git archive --format=zip -o "release\mmts-update-%BUILD_DATE%.zip" HEAD -- . ^
  ":(exclude)docs" ":(exclude)scripts" ":(exclude)README.md" ":(exclude).gitignore" ^
  ":(exclude)router.php" ":(exclude)start-php-local.bat"
if errorlevel 1 (
  echo Build failed.
  exit /b 1
)

echo Built release\mmts-update-%BUILD_DATE%.zip
