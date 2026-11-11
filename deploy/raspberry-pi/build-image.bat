@echo off
rem Builds a flashable Air_Box Raspberry Pi OS image with Docker Desktop.
rem Output: dist\airbox-pi-DATE.img.xz plus dist\airbox-credentials.txt
rem Takes roughly 15-30 minutes and about 6 GB of free disk space.
rem
rem Optional settings - set them in this window before running, e.g.
rem   set AIRBOX_WIFI_PASSWORD=secret123   (default: open network)
rem   set AIRBOX_COUNTRY=GB                (default: AE)
rem See deploy\raspberry-pi\README.md for the full list.
setlocal
cd /d "%~dp0..\.."

docker version >nul 2>&1
if errorlevel 1 (
  echo.
  echo Docker Desktop is not running. Start it, wait until it says "running",
  echo then run this script again.
  echo.
  pause
  exit /b 1
)

if not exist dist mkdir dist
docker volume create airbox-build >nul

rem "-e NAME" with no value passes NAME through only if it's set here.
rem The script is piped through tr so Windows CRLF line endings can't break it.
docker run --rm --privileged --platform linux/amd64 ^
  -v airbox-build:/work -v "%CD%:/src:ro" -v "%CD%\dist:/out" ^
  -e AIRBOX_SSID -e AIRBOX_WIFI_PASSWORD -e AIRBOX_COUNTRY -e AIRBOX_CHANNEL ^
  -e AIRBOX_HOSTNAME -e AIRBOX_ADMIN_PASSWORD -e AIRBOX_SSH_PASSWORD ^
  debian:trixie-slim bash -c "tr -d '\r' < /src/deploy/raspberry-pi/build-image.sh > /tmp/build.sh && bash /tmp/build.sh"

if errorlevel 1 (
  echo.
  echo Build failed - see the messages above.
  pause
  exit /b 1
)

echo.
echo Done. The image and airbox-credentials.txt are in:
echo   %CD%\dist
echo Flash the .img.xz with Raspberry Pi Imager ("Use custom").
pause
