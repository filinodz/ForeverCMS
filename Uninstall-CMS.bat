@echo off
rem Forever CMS: the launcher starts only the support site again. Close the launcher first.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup\Uninstall-CMS.ps1"
echo.
pause
