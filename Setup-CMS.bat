@echo off
rem Forever CMS: one-time setup (website database, website administrators, launcher). See README.txt.
rem Close the launcher first: it keeps its settings in memory and would write the old ones back.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup\Setup-CMS.ps1" %*
echo.
pause
