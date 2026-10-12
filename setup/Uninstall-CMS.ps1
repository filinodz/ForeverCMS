# Forever CMS: undoes what Setup-CMS changed in the launcher's files. Run through Uninstall-CMS.bat.
# The website database (forever_site) and the cms folder are kept: delete them yourself if you want them gone
# (the server databases auth, characters*, world and hotfixes were never changed by the CMS's setup).
$ErrorActionPreference = 'Continue'
$Cms = Split-Path -Parent $PSScriptRoot
$Root = Split-Path -Parent $Cms
$CmsUrl = 'http://127.0.0.1:8080'

function Good($text) { Write-Host "   $text" -ForegroundColor Green }
function Info($text) { Write-Host "   $text" }
function Save-Json($path, $text) { [IO.File]::WriteAllText($path, $text, (New-Object System.Text.UTF8Encoding($false))) }

$local = Join-Path $Root 'localservers.json'
if (Test-Path -LiteralPath $local)
{
    $text = [IO.File]::ReadAllText($local)
    $hook = '\"{here}\\cms\\forever_web.py\" '
    if ($text.Contains($hook)) { Save-Json $local $text.Replace($hook, ''); Good 'the launcher''s Website line starts only the support site again' }
    else { Info 'localservers.json does not start the CMS: nothing to undo' }
}

$servers = Join-Path $Root 'servers.json'
if (Test-Path -LiteralPath $servers)
{
    $text = [IO.File]::ReadAllText($servers)
    $link = '"website": "' + $CmsUrl + '"'
    if ($text.Contains($link)) { Save-Json $servers $text.Replace($link, '"website": "https://github.com/advocaite/TrinityCore/tree/forever"'); Good 'the launcher''s website link is the original one again' }
    else { Info 'servers.json does not link to the CMS: nothing to undo' }
}

Write-Host ''
Info 'To remove the website data too: stop the servers, then run in mysql\bin'
Info '   mysql -uroot -P3317 -h 127.0.0.1 -e "DROP DATABASE forever_site"'
Info 'and delete the cms folder.'
exit 0
