@echo off
setlocal
cd /d "%~dp0"
rem Website-only release: preserve the existing core and game launcher.
powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Install-ForeverCMS.ps1" -SelectFolder -ReplaceExisting -SkipCoreUpdate -SkipLauncherUpdate %*
set "EXITCODE=%ERRORLEVEL%"
echo.
if not "%EXITCODE%"=="0" echo Installation stopped with error code %EXITCODE%.
pause
exit /b %EXITCODE%
