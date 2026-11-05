@echo off
rem Builds the cPanel upload package (changed website files only). See build-release.ps1.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0build-release.ps1"
