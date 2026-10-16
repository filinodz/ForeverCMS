[CmdletBinding()]
param(
    [string]$RepackPath = '',
    [switch]$ReplaceExisting,
    [switch]$ResetSiteDatabase,
    [switch]$SkipLauncherUpdate,
    [switch]$SkipCoreUpdate,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$PackageRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$PayloadRoot = Join-Path $PackageRoot 'payload'
$PayloadCms = Join-Path $PayloadRoot 'cms'
$PayloadLauncher = Join-Path $PayloadRoot 'ForeverLauncher.exe'
$PayloadCore = Join-Path $PayloadRoot 'server\worldserver.exe'

function Write-Step([string]$Text) { Write-Host "`n==> $Text" -ForegroundColor Cyan }
function Write-Good([string]$Text) { Write-Host "    $Text" -ForegroundColor Green }
function Stop-Install([string]$Text) { throw $Text }

Write-Host 'Forever CMS installer for Forever Launcher/Repack' -ForegroundColor Yellow
Write-Host 'Adaptation, integration and visual design by FilinoDZ.'

if ([string]::IsNullOrWhiteSpace($RepackPath)) {
    $candidatePaths = [System.Collections.Generic.List[string]]::new()
    if (-not [string]::IsNullOrWhiteSpace($env:FOREVER_REPACK_PATH)) {
        $candidatePaths.Add($env:FOREVER_REPACK_PATH)
    }
    $candidatePaths.Add((Get-Location).Path)
    $candidatePaths.Add((Split-Path -Parent $PackageRoot))

    foreach ($drive in [IO.DriveInfo]::GetDrives() | Where-Object { $_.IsReady }) {
        $wowRoot = Join-Path $drive.RootDirectory.FullName 'WoWForever'
        if (-not (Test-Path -LiteralPath $wowRoot -PathType Container)) { continue }
        foreach ($versionFolder in Get-ChildItem -LiteralPath $wowRoot -Directory -Filter 'ForeverRepack_*' -ErrorAction SilentlyContinue) {
            $candidatePaths.Add($versionFolder.FullName)
            $candidatePaths.Add((Join-Path $versionFolder.FullName 'ForeverRepack'))
        }
    }

    $RepackPath = $candidatePaths |
        Select-Object -Unique |
        Where-Object {
            (Test-Path -LiteralPath (Join-Path $_ 'ForeverLauncher.exe') -PathType Leaf) -and
            (Test-Path -LiteralPath (Join-Path $_ 'mysql\bin\mysql.exe') -PathType Leaf) -and
            (Test-Path -LiteralPath (Join-Path $_ 'server') -PathType Container)
        } |
        Select-Object -First 1
}

if (-not (Test-Path -LiteralPath $RepackPath -PathType Container)) {
    Stop-Install "ForeverRepack was not detected. Pass the correct folder with -RepackPath or set FOREVER_REPACK_PATH."
}

$ResolvedRepack = (Resolve-Path -LiteralPath $RepackPath).Path.TrimEnd('\')
$required = @(
    'ForeverLauncher.exe',
    'localservers.json',
    'servers.json',
    'mysql\bin\mysql.exe',
    'mysql\bin\mysqld.exe',
    'web\python\python.exe',
    'server'
)

Write-Step "Validating $ResolvedRepack"
foreach ($relative in $required) {
    $candidate = Join-Path $ResolvedRepack $relative
    if (-not (Test-Path -LiteralPath $candidate)) { Stop-Install "This does not look like a complete ForeverRepack. Missing: $relative" }
}
if (-not (Test-Path -LiteralPath (Join-Path $PayloadCms 'www\index.php'))) { Stop-Install 'The package payload is incomplete (payload\cms missing).' }
if (-not $SkipLauncherUpdate -and -not (Test-Path -LiteralPath $PayloadLauncher -PathType Leaf)) { Stop-Install 'The compatible ForeverLauncher.exe is missing from the payload.' }
Write-Good 'ForeverRepack and package payload validated.'
if (-not $SkipCoreUpdate -and -not (Test-Path -LiteralPath $PayloadCore -PathType Leaf)) { Stop-Install 'The world authoring-compatible worldserver is missing. Use -SkipCoreUpdate only with your own patched core.' }

$TargetCms = Join-Path $ResolvedRepack 'cms'
$TargetCmsFull = [IO.Path]::GetFullPath($TargetCms).TrimEnd('\')
$ExpectedCms = ([IO.Path]::GetFullPath((Join-Path $ResolvedRepack 'cms'))).TrimEnd('\')
if (-not $TargetCmsFull.Equals($ExpectedCms, [StringComparison]::OrdinalIgnoreCase)) { Stop-Install 'Unsafe CMS target path.' }

if ((Test-Path -LiteralPath $TargetCms) -and -not $ReplaceExisting) {
    Stop-Install 'A cms folder already exists. Re-run with -ReplaceExisting to back it up and replace it.'
}
if ($ResetSiteDatabase -and -not $ReplaceExisting -and (Test-Path -LiteralPath $TargetCms)) {
    Stop-Install '-ResetSiteDatabase on an existing installation also requires -ReplaceExisting.'
}

if ($DryRun) {
    Write-Step 'Dry run'
    Write-Host "    Target: $ResolvedRepack"
    Write-Host "    Existing CMS: $(Test-Path -LiteralPath $TargetCms)"
    Write-Host "    Replace existing CMS: $([bool]$ReplaceExisting)"
    Write-Host "    Reset only forever_site: $([bool]$ResetSiteDatabase)"
    Write-Host "    Update launcher: $(-not $SkipLauncherUpdate)"
    Write-Host "    Update worldserver: $(-not $SkipCoreUpdate)"
    Write-Good 'Validation successful. No file or database was changed.'
    exit 0
}

if (Get-Process -Name 'ForeverLauncher' -ErrorAction SilentlyContinue) {
    Stop-Install 'Forever Launcher is running. Close it and start the installer again.'
}
$CmsProcesses = Get-CimInstance Win32_Process | Where-Object {
    $_.Name -in @('php.exe','php-cgi.exe','python.exe','pythonw.exe') -and
    (($_.ExecutablePath -and $_.ExecutablePath.StartsWith($TargetCmsFull + '\', [StringComparison]::OrdinalIgnoreCase)) -or
     ($_.CommandLine -and $_.CommandLine.IndexOf($TargetCmsFull, [StringComparison]::OrdinalIgnoreCase) -ge 0))
}
if ($CmsProcesses) { Stop-Install 'Stop the repack Website / CMS web server before updating. No files have been replaced.' }
if (-not $SkipCoreUpdate) {
    $WorldTarget = [IO.Path]::GetFullPath((Join-Path $ResolvedRepack 'server\worldserver.exe'))
    $running = Get-CimInstance Win32_Process -Filter "name='worldserver.exe'" | Where-Object { $_.ExecutablePath -and [IO.Path]::GetFullPath($_.ExecutablePath).Equals($WorldTarget,[StringComparison]::OrdinalIgnoreCase) }
    if ($running) { Stop-Install 'Stop all worldserver realms before installing the compatible core. No files have been replaced.' }
}

$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupRoot = Join-Path $ResolvedRepack "ForeverCMS-Backups\$Stamp"
New-Item -ItemType Directory -Path $BackupRoot -Force | Out-Null

Write-Step "Creating recoverable backup at $BackupRoot"
foreach ($relative in @('ForeverLauncher.exe', 'localservers.json', 'servers.json')) {
    $source = Join-Path $ResolvedRepack $relative
    if (Test-Path -LiteralPath $source -PathType Leaf) { Copy-Item -LiteralPath $source -Destination (Join-Path $BackupRoot $relative) -Force }
}
if (-not $SkipCoreUpdate) {
    New-Item -ItemType Directory -Path (Join-Path $BackupRoot 'server') -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $ResolvedRepack 'server\worldserver.exe') -Destination (Join-Path $BackupRoot 'server\worldserver.exe')
}
if (Test-Path -LiteralPath $TargetCms) {
    Move-Item -LiteralPath $TargetCmsFull -Destination (Join-Path $BackupRoot 'cms')
}
Write-Good 'Backup complete.'

Write-Step 'Installing Forever CMS'
Copy-Item -LiteralPath $PayloadCms -Destination $TargetCms -Recurse -Force
if ($ReplaceExisting -and -not $ResetSiteDatabase) {
    foreach ($relative in @('www\includes\config.php','www\includes\config.settings.php','www\includes\config.secret.php','www\includes\config.database.php','www\includes\realm_config.php','www\includes\config.mail.php','www\includes\config.cap.php','www\includes\ra.conf.php','www\includes\armory_config.php','www\includes\auth_config.php')) {
        $saved = Join-Path (Join-Path $BackupRoot 'cms') $relative
        if (Test-Path -LiteralPath $saved -PathType Leaf) { Copy-Item -LiteralPath $saved -Destination (Join-Path $TargetCms $relative) -Force }
    }
    Write-Good 'Existing private configuration and realm settings preserved.'
}
if (-not $SkipCoreUpdate) {
    Copy-Item -LiteralPath $PayloadCore -Destination (Join-Path $ResolvedRepack 'server\worldserver.exe') -Force
    Write-Good 'World authoring-compatible worldserver installed; server configuration preserved.'
}
if (-not $SkipLauncherUpdate) {
    Copy-Item -LiteralPath $PayloadLauncher -Destination (Join-Path $ResolvedRepack 'ForeverLauncher.exe') -Force
    Write-Good 'Compatible Forever Launcher installed.'
}
Copy-Item -LiteralPath (Join-Path $PackageRoot 'README.md') -Destination (Join-Path $ResolvedRepack 'FOREVER-CMS-README.md') -Force
Write-Good 'CMS files installed.'

Write-Step 'Preparing the website database and launcher integration'
$SetupScript = Join-Path $TargetCms 'setup\Setup-CMS.ps1'
$setupArgs = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $SetupScript)
if ($ResetSiteDatabase) { $setupArgs += '-FreshSiteDatabase' }
& powershell.exe @setupArgs
if ($LASTEXITCODE -ne 0) { Stop-Install "CMS setup failed with exit code $LASTEXITCODE. Your backup is in $BackupRoot" }

Write-Step 'Final verification'
$localText = [IO.File]::ReadAllText((Join-Path $ResolvedRepack 'localservers.json'))
$serversText = [IO.File]::ReadAllText((Join-Path $ResolvedRepack 'servers.json'))
if ($localText -notmatch 'forever_web\.py') { Stop-Install 'The CMS was copied, but localservers.json was not connected. See the setup output above.' }
if ($serversText -notmatch 'http://127\.0\.0\.1:8080') { Stop-Install 'The CMS was copied, but the launcher website URL was not updated. See the setup output above.' }
if (-not (Test-Path -LiteralPath (Join-Path $TargetCms 'www\includes\config.secret.php'))) {
    Write-Good 'A private site secret will be generated on first launch.'
}

Write-Host ''
Write-Host 'Forever CMS installation completed.' -ForegroundColor Green
Write-Host '1. Start Forever Launcher.'
Write-Host '2. Open LOCAL SERVER and click Start all.'
Write-Host '3. Open http://127.0.0.1:8080'
Write-Host "Backup: $BackupRoot"
exit 0
