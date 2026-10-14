[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$RepackPath,
    [Parameter(Mandatory=$true)][string]$CorePath,
    [Parameter(Mandatory=$true)][string]$RuntimePayload,
    [string]$Version = '1.5.0'
)
$ErrorActionPreference = 'Stop'
if ($Version -notmatch '^\d+\.\d+\.\d+$') { throw 'Invalid release version.' }
$repo = Split-Path -Parent $PSScriptRoot
$repack = (Resolve-Path -LiteralPath $RepackPath).Path
$coreSource = Join-Path (Resolve-Path -LiteralPath $CorePath).Path 'source'
$coreBinary = Join-Path $CorePath 'bin\worldserver.exe'
$runtime = (Resolve-Path -LiteralPath $RuntimePayload).Path
foreach ($file in @($coreBinary,(Join-Path $runtime 'cms\php\php.exe'),(Join-Path $runtime 'ForeverLauncher.exe'))) {
    if (-not (Test-Path -LiteralPath $file -PathType Leaf)) { throw "Missing release input: $file" }
}
if (git -C $repo status --porcelain) { throw 'Commit the CMS changes before packaging a release.' }
git -C $coreSource apply --reverse --check (Join-Path $repo 'core\forever_creator_world.patch')
if ($LASTEXITCODE -ne 0) { throw 'The core working source does not match the published bridge patch.' }
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$stage = Join-Path $repack "packaging\ForeverCMS-$Version-$stamp"
$sourceStage = Join-Path $repack "packaging\ForeverCore-source-$Version-$stamp"
$dist = Join-Path $repack 'dist'
New-Item -ItemType Directory -Path $stage,$sourceStage,$dist -Force | Out-Null
$cms = Join-Path $stage 'payload\cms'
New-Item -ItemType Directory -Path $cms,(Join-Path $stage 'payload\server') | Out-Null
$archive = Join-Path $stage 'cms-source.zip'
git -C $repo archive --format=zip "--output=$archive" HEAD
if ($LASTEXITCODE -ne 0) { throw 'CMS source archive failed.' }
Expand-Archive -LiteralPath $archive -DestinationPath $cms
Remove-Item -LiteralPath $archive
Copy-Item -LiteralPath (Join-Path $runtime 'cms\php') -Destination $cms -Recurse
Copy-Item -LiteralPath (Join-Path $runtime 'ForeverLauncher.exe') -Destination (Join-Path $stage 'payload\ForeverLauncher.exe')
Copy-Item -LiteralPath $coreBinary -Destination (Join-Path $stage 'payload\server\worldserver.exe')
foreach ($name in @('Install-ForeverCMS.ps1','Install-ForeverCMS.bat')) {
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot $name) -Destination (Join-Path $stage $name)
}
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'README.md') -Destination (Join-Path $stage 'README.md')
Copy-Item -LiteralPath (Join-Path $repo 'LICENSE-SahtoutCMS.txt') -Destination $stage
Copy-Item -LiteralPath (Join-Path $repo 'core\COPYING') -Destination (Join-Path $stage 'LICENSE-ForeverCore.txt')
$sourceZip = Join-Path $dist "ForeverCore-source-for-ForeverCMS-$Version.zip"
$cmsZip = Join-Path $dist "ForeverCMS-$Version-for-ForeverRepack.zip"
if ((Test-Path -LiteralPath $sourceZip) -or (Test-Path -LiteralPath $cmsZip)) { throw 'Release archives already exist; they will not be overwritten.' }
# Preserve all corresponding build sources and current SQL, without obsolete
# migration archives that are not used to compile or install the tested core.
$corePaths = @(& git -C $coreSource ls-tree --name-only HEAD | Where-Object { $_ -ne 'sql' })
$corePaths += @(& git -C $coreSource ls-tree --name-only HEAD sql/ | Where-Object { $_ -ne 'sql/old' })
git -C $coreSource archive --format=zip "--output=$sourceZip" HEAD @corePaths
if ($LASTEXITCODE -ne 0) { throw 'Core source archive failed.' }
Expand-Archive -LiteralPath $sourceZip -DestinationPath $sourceStage
foreach ($relative in @('src\server\shared\DataStores\DB2Store.h','src\server\shared\DataStores\DB2Store.cpp','src\server\game\World\World.cpp')) {
    Copy-Item -LiteralPath (Join-Path $coreSource $relative) -Destination (Join-Path $sourceStage $relative) -Force
}
Copy-Item -LiteralPath (Join-Path $repo 'core\README.md') -Destination (Join-Path $sourceStage 'FOREVERCMS-BRIDGE.md')
Compress-Archive -Path (Join-Path $sourceStage '*') -DestinationPath $sourceZip -Force
$manifest = Get-ChildItem -LiteralPath $stage -Recurse -File | ForEach-Object {
    '{0}  {1}' -f (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant(), $_.FullName.Substring($stage.Length+1).Replace('\','/')
}
[IO.File]::WriteAllLines((Join-Path $stage 'MANIFEST.sha256'), $manifest, [Text.UTF8Encoding]::new($false))
Compress-Archive -Path (Join-Path $stage '*') -DestinationPath $cmsZip
Get-Item -LiteralPath $cmsZip,$sourceZip | Select-Object FullName,Length
Write-Output "Installer staging folder: $stage"
