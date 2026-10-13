# Forever CMS: one-time setup. Run through Setup-CMS.bat (no administrator rights needed).
#   1. creates the website database (forever_site) in the repack's MySQL and lets the "trinity" user use it
#   2. gives every game account its website row; GM level 3 accounts become website administrators
#   3. makes the launcher's "Website" line start the CMS together with the support site (localservers.json)
#      and points the launcher's website link at the CMS (servers.json); -NoLauncher skips this step
# Starts the repack's MySQL for a moment if it is not running. Safe to run again: nothing is overwritten.
# -FreshSiteDatabase explicitly deletes and recreates ONLY forever_site. It never deletes game databases.
param([switch]$NoLauncher, [switch]$FreshSiteDatabase)
$ErrorActionPreference = 'Continue'
$Cms = Split-Path -Parent $PSScriptRoot
$Root = Split-Path -Parent $Cms
$MysqlBin = Join-Path $Root 'mysql\bin'
$Mysql = Join-Path $MysqlBin 'mysql.exe'
$MysqlPort = 3317
$CmsUrl = 'http://127.0.0.1:8080'

function Good($text) { Write-Host "   $text" -ForegroundColor Green }
function Info($text) { Write-Host "   $text" }
function Fail($text) { Write-Host ''; Write-Host "ERROR: $text" -ForegroundColor Red; exit 1 }
function Test-Port($port) { try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1', $port); $c.Close(); return $true } catch { return $false } }
function Save-Json($path, $text) { [IO.File]::WriteAllText($path, $text, (New-Object System.Text.UTF8Encoding($false))) }

if (-not (Test-Path -LiteralPath (Join-Path $Cms 'php\php.exe'))) { Fail 'cms\php\php.exe is missing.' }
if (-not (Test-Path -LiteralPath $Mysql)) { Fail "the repack's MySQL was not found next to the cms folder ($MysqlBin)." }

foreach ($folder in 'logs', 'tmp\sessions') { New-Item -ItemType Directory -Force (Join-Path $Cms $folder) | Out-Null }

$startedMysql = $false
if (Test-Port $MysqlPort)
{
    $reportedDataDir = (& $Mysql -uroot "-P$MysqlPort" -h 127.0.0.1 -N -e "SELECT @@datadir;" 2>$null | Select-Object -First 1)
    if ($LASTEXITCODE -ne 0 -or -not $reportedDataDir) { Fail "port $MysqlPort is in use, but it is not the expected accessible Forever MySQL." }
    $expectedDataDir = [IO.Path]::GetFullPath((Join-Path $Root 'mysql\data')).TrimEnd('\', '/')
    $actualDataDir = [IO.Path]::GetFullPath(([string]$reportedDataDir).Trim()).TrimEnd('\', '/')
    if (-not $actualDataDir.Equals($expectedDataDir, [StringComparison]::OrdinalIgnoreCase))
    {
        Fail "another repack's MySQL is already using port $MysqlPort ($actualDataDir). Stop it before setting up this repack ($expectedDataDir)."
    }
}
else
{
    $mysqlDir = Join-Path $Root 'mysql'
    Start-Process -FilePath (Join-Path $MysqlBin 'mysqld.exe') -WindowStyle Hidden -ArgumentList @('--no-defaults', "`"--basedir=$mysqlDir`"", "`"--datadir=$mysqlDir\data`"", "--port=$MysqlPort", '--bind-address=127.0.0.1', '--mysqlx=OFF', '--disable-log-bin')
    $startedMysql = $true
    for ($i = 0; $i -lt 60 -and -not (Test-Port $MysqlPort); $i++) { Start-Sleep 1 }
    if (-not (Test-Port $MysqlPort)) { Fail "MySQL did not start (port $MysqlPort). Is another program using it?" }
}
try
{
    # 1. the website database
    if ($FreshSiteDatabase)
    {
        Info 'fresh install requested: resetting only the forever_site website database'
        & $Mysql -uroot "-P$MysqlPort" -h 127.0.0.1 -e "DROP DATABASE IF EXISTS forever_site;"
        if ($LASTEXITCODE -ne 0) { Fail 'could not reset the forever_site website database.' }
    }
    Get-Content -LiteralPath (Join-Path $Cms 'sql\forever_site.sql') -Raw -Encoding UTF8 | & $Mysql -uroot "-P$MysqlPort" -h 127.0.0.1 --default-character-set=utf8mb4
    if ($LASTEXITCODE -ne 0) { Fail 'could not create the website database (see the message above).' }
    Get-Content -LiteralPath (Join-Path $Cms 'sql\forever_shared_shop.sql') -Raw -Encoding UTF8 | & $Mysql -uroot "-P$MysqlPort" -h 127.0.0.1 --default-character-set=utf8mb4
    if ($LASTEXITCODE -ne 0) { Fail 'could not synchronize the ForeverCMS and in-game shop catalogs.' }
    & $Mysql -uroot "-P$MysqlPort" -h 127.0.0.1 -e "GRANT ALL PRIVILEGES ON forever_site.* TO 'trinity'@'127.0.0.1'; GRANT ALL PRIVILEGES ON forever_site.* TO 'trinity'@'localhost'; FLUSH PRIVILEGES;"
    if ($LASTEXITCODE -ne 0) { Fail 'could not give the trinity user access to forever_site.' }
    Good 'website database forever_site is ready'

    # 2. website rows; GM level 3 accounts (but not the launcher's console account) administer the website
    $sql = "INSERT INTO forever_site.user_currencies (account_id, username, email, role) " +
           "SELECT a.id, a.username, LOWER(COALESCE(b.email, a.email)), " +
           "IF(COALESCE(MAX(aa.SecurityLevel), 0) >= 3 AND COALESCE(b.email, a.email) NOT LIKE '%@LAUNCHER', 'admin', 'player') " +
           "FROM auth.account a LEFT JOIN auth.battlenet_accounts b ON b.id = a.battlenet_account " +
           "LEFT JOIN auth.account_access aa ON aa.AccountID = a.id GROUP BY a.id, a.username, b.email, a.email " +
           "ON DUPLICATE KEY UPDATE username = VALUES(username), email = VALUES(email), role = IF(VALUES(role) = 'admin', 'admin', role);"
    & $Mysql -uroot "-P$MysqlPort" -h 127.0.0.1 -e $sql
    if ($LASTEXITCODE -ne 0) { Fail 'could not prepare the website accounts.' }
    $admins = (& $Mysql -uroot "-P$MysqlPort" -h 127.0.0.1 -N -e "SELECT GROUP_CONCAT(email SEPARATOR ', ') FROM forever_site.user_currencies WHERE role = 'admin'")
    if ($admins -and $admins -ne 'NULL') { Good "website administrators: $admins" } else { Info 'no GM level 3 account yet: nobody administers the website (run this again after creating one).' }
}
finally
{
    if ($startedMysql) { & (Join-Path $MysqlBin 'mysqladmin.exe') -uroot "-P$MysqlPort" -h 127.0.0.1 shutdown | Out-Null }
}

# 3. the launcher
if (-not $NoLauncher)
{
    $backupDir = Join-Path $PSScriptRoot 'backups'
    New-Item -ItemType Directory -Force $backupDir | Out-Null
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

    $local = Join-Path $Root 'localservers.json'
    if (Test-Path -LiteralPath $local)
    {
        $text = [IO.File]::ReadAllText($local)
        if ($text -match 'forever_web\.py') { Info 'the launcher already starts the CMS with its Website' }
        elseif ($text -match '"webArgs":\s*"\\"\{here\}\\\\web\\\\support_server\.py\\"')
        {
            Copy-Item -LiteralPath $local -Destination (Join-Path $backupDir "localservers.$stamp.json")
            $text = $text.Replace('"webArgs": "\"{here}\\web\\support_server.py\"', '"webArgs": "\"{here}\\cms\\forever_web.py\" \"{here}\\web\\support_server.py\"')
            Save-Json $local $text
            Good 'the launcher''s Website line now starts the CMS too (LOCAL SERVER tab: Start all)'
        }
        else { Info 'localservers.json has its own website command: not changed. Start the CMS with cms\Start-CMS.bat.' }
    }
    else { Info 'localservers.json not found (the repack''s Setup makes it). Start the CMS with cms\Start-CMS.bat.' }

    $servers = Join-Path $Root 'servers.json'
    if (Test-Path -LiteralPath $servers)
    {
        $text = [IO.File]::ReadAllText($servers)
        if ($text -match [regex]::Escape($CmsUrl)) { Info 'the launcher''s website link already opens the CMS' }
        elseif ($text -match '"website":\s*"https://github\.com/advocaite/TrinityCore/tree/forever"')
        {
            Copy-Item -LiteralPath $servers -Destination (Join-Path $backupDir "servers.$stamp.json")
            $text = $text -replace '"website":\s*"https://github\.com/advocaite/TrinityCore/tree/forever"', ('"website": "' + $CmsUrl + '"')
            Save-Json $servers $text
            Good "the launcher's website link now opens $CmsUrl"
        }
        else { Info 'servers.json has its own website link: not changed.' }
    }
}

Write-Host ''
Good "Setup finished. Website: $CmsUrl"
Info 'Start it from the launcher (LOCAL SERVER tab, Start all) or with cms\Start-CMS.bat.'
Info 'Log in with your game e-mail and password. Details: cms\README.txt'
exit 0
