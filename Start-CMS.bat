@echo off
rem Forever CMS: starts the website on http://127.0.0.1:8080 with the PHP that comes in this folder.
rem MySQL must be running (launcher, LOCAL SERVER tab). Close this window to stop the website.
rem The launcher starts the CMS by itself once Setup-CMS.bat has run; this is for starting it alone.
setlocal
set "FOREVER_CMS=%~dp0"
if "%FOREVER_CMS:~-1%"=="\" set "FOREVER_CMS=%FOREVER_CMS:~0,-1%"
set "FOREVER_CMS=%FOREVER_CMS:\=/%"
if "%FOREVER_CMS_BIND%"=="" set "FOREVER_CMS_BIND=127.0.0.1"
if "%FOREVER_CMS_PORT%"=="" set "FOREVER_CMS_PORT=8080"
cd /d "%~dp0"
if not exist tmp\sessions mkdir tmp\sessions
if not exist logs mkdir logs
echo.
echo   Forever CMS is running: http://%FOREVER_CMS_BIND%:%FOREVER_CMS_PORT%
echo   Close this window to stop it.
echo.
php\php.exe -c php\php.ini -S %FOREVER_CMS_BIND%:%FOREVER_CMS_PORT% -t www router.php
