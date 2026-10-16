$ErrorActionPreference='Stop'
$repo=Split-Path -Parent $PSScriptRoot
$fixtureRoot=Join-Path ([IO.Path]::GetTempPath()) ('ForeverCMS-repack-fixture-'+[guid]::NewGuid().ToString('N'))
$repack=Join-Path $fixtureRoot 'repack'
$package=Join-Path $fixtureRoot 'package'
try {
    # Deliberately non-executable sentinels. DryRun must never launch any of them.
    foreach($relative in @('ForeverLauncher.exe','localservers.json','servers.json','mysql/bin/mysql.exe','mysql/bin/mysqld.exe','web/python/python.exe','server/worldserver.exe')){
        $file=Join-Path $repack $relative;[void](New-Item -ItemType Directory -Path (Split-Path -Parent $file) -Force);[IO.File]::WriteAllText($file,'fixture only')
    }
    foreach($relative in @('payload/cms/www/index.php','payload/ForeverLauncher.exe','payload/server/worldserver.exe')){
        $file=Join-Path $package $relative;[void](New-Item -ItemType Directory -Path (Split-Path -Parent $file) -Force);[IO.File]::WriteAllText($file,'fixture only')
    }
    Copy-Item -LiteralPath (Join-Path $repo 'installer/Install-ForeverCMS.ps1') -Destination $package
    $before=@{};Get-ChildItem -LiteralPath $repack -File -Recurse | ForEach-Object {$before[$_.FullName]=(Get-FileHash -LiteralPath $_.FullName).Hash}
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $package 'Install-ForeverCMS.ps1') -RepackPath $repack -DryRun
    if($LASTEXITCODE-ne 0){throw 'Repack package validation failed'}
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $package 'Install-ForeverCMS.ps1') -RepackPath $repack -DryRun -SkipCoreUpdate -SkipLauncherUpdate
    if($LASTEXITCODE-ne 0){throw 'CMS-only repack validation failed'}
    foreach($file in $before.Keys){if((Get-FileHash -LiteralPath $file).Hash-ne $before[$file]){throw 'DryRun changed a repack file'}}
    if(Test-Path -LiteralPath (Join-Path $repack 'cms')){throw 'DryRun installed files'}
    'PASS complete repack package validation'
    'PASS CMS-only repack validation without replacing core or launcher'
    'PASS dry run leaves repack files untouched'
} finally {
    $resolved=[IO.Path]::GetFullPath($fixtureRoot)
    $temporary=[IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\','/')+[IO.Path]::DirectorySeparatorChar
    if(-not $resolved.StartsWith($temporary,[StringComparison]::OrdinalIgnoreCase)-or (Split-Path -Leaf $resolved)-notmatch '^ForeverCMS-repack-fixture-[a-f0-9]{32}$'){throw 'Unsafe fixture cleanup path'}
    if(Test-Path -LiteralPath $resolved){Remove-Item -LiteralPath $resolved -Recurse -Force}
}
