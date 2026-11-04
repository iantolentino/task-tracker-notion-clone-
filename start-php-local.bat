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

echo Starting Creatives Ticketing System on http://127.0.0.1:8000
echo Open http://127.0.0.1:8000/ and sign in with your configured super-admin.
start "" "http://127.0.0.1:8000/"
"%PHP_EXE%" -S 127.0.0.1:8000 router.php
pause
