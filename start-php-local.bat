@echo off
setlocal
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
  if exist "C:\xampp\php\php.exe" (
    set "PHP_EXE=C:\xampp\php\php.exe"
  ) else (
    echo PHP was not found.
    echo Install XAMPP, then add C:\xampp\php to your PATH or run this from the XAMPP Shell.
    pause
    exit /b 1
  )
) else (
  set "PHP_EXE=php"
)

echo Starting Tasks Tracker on http://127.0.0.1:8000
echo First-time setup: open http://127.0.0.1:8000/setup.php
start "" "http://127.0.0.1:8000/setup.php"
"%PHP_EXE%" -S 127.0.0.1:8000 router.php
pause
