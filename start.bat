@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

rem Find php.exe: PATH first, then the winget install folder (works even
rem before you've restarted your terminal after installing PHP).
set "PHP_EXE="
for /f "delims=" %%P in ('where php 2^>nul') do set "PHP_EXE=%%P"

if not defined PHP_EXE (
  for /f "delims=" %%P in ('dir /s /b "%LOCALAPPDATA%\Microsoft\WinGet\Packages\php.exe" 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
)

if not defined PHP_EXE (
  echo.
  echo Could not find PHP. Install it first, e.g.:
  echo   winget install PHP.PHP.8.5
  echo then run this script again.
  echo.
  pause
  exit /b 1
)

echo.
echo Starting air.box with "!PHP_EXE!"
echo   http://localhost:8000
echo Press Ctrl+C to stop the server.
echo.

start "" http://localhost:8000
"!PHP_EXE!" -S localhost:8000 router.php

pause
