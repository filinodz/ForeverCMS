[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$OutputDirectory,
    [Parameter(Mandatory=$true)][string]$PhpRuntime,
    [string]$Version='1.6.0'
)
$ErrorActionPreference='Stop'
if($Version-notmatch '^\d+\.\d+\.\d+$'){throw 'Invalid version'}
$repo=Split-Path -Parent $PSScriptRoot
if(git -C $repo status --porcelain){throw 'Commit source changes before packaging'}
$commit=git -C $repo rev-parse HEAD
$runtime=(Resolve-Path -LiteralPath $PhpRuntime).Path
foreach($required in @('php.exe','php.ini','license.txt')){if(-not(Test-Path -LiteralPath (Join-Path $runtime $required))){throw "PHP runtime missing $required"}}
if(Get-ChildItem -LiteralPath $runtime -Recurse -Force|Where-Object {$_.Attributes-band [IO.FileAttributes]::ReparsePoint}){throw 'Linked runtime files are not accepted'}
$output=[IO.Path]::GetFullPath($OutputDirectory)
[void](New-Item -ItemType Directory -Path $output -Force)
$names=@("ForeverCMS-$Version-Standalone-Windows.zip","ForeverCMS-$Version-for-ForeverRepack.zip","ForeverCMS-$Version-SHA256SUMS.txt")
foreach($name in $names){if(Test-Path -LiteralPath (Join-Path $output $name)){throw "Refusing to overwrite $name"}}
$stage=Join-Path $output ("staging-$Version-"+[guid]::NewGuid().ToString('N'))
$standalone=Join-Path $stage 'standalone'
$repack=Join-Path $stage 'repack'
[void](New-Item -ItemType Directory -Path $stage,$standalone,$repack)
$archive=Join-Path $stage 'committed-source.zip'
git -C $repo archive --format=zip "--output=$archive" HEAD
if($LASTEXITCODE-ne 0){throw 'Source snapshot failed'}
Expand-Archive -LiteralPath $archive -DestinationPath $standalone
$runtimeFiles=@(Get-ChildItem -LiteralPath $runtime -Recurse -File)
foreach($file in $runtimeFiles){
    $relative=$file.FullName.Substring($runtime.TrimEnd('\','/').Length+1)
    if($relative-notmatch '(?i)\.(exe|dll|ini|txt|md)$'){continue}
    if($relative-match '(?i)(^|[\\/])(logs|tmp|sessions|cache)([\\/]|$)'){continue}
    $target=Join-Path (Join-Path $standalone 'php') $relative
    [void](New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force)
    Copy-Item -LiteralPath $file.FullName -Destination $target
}
foreach($relative in @('logs','tmp/sessions')){[void](New-Item -ItemType Directory -Path (Join-Path $standalone $relative) -Force)}
function Write-Manifest([string]$root){
    $files=@(Get-ChildItem -LiteralPath $root -File -Recurse -Force|Where-Object {$_.Name-ne 'SHA256SUMS.txt'})
    foreach($file in $files){
        $relative=$file.FullName.Substring($root.Length+1).Replace('\','/')
        if($relative-match '(?i)(^|/)(config\.(secret|database)\.php|\.env(?:\..*)?)$|(^|/)(logs|tmp|cache|backups|uploads)/|\.(pem|pfx|p12|key|log|pid|dmp)$'){throw "Private/runtime file rejected: $relative"}
        if($relative-match '(?i)(^|/)(worldserver|bnetserver|ForeverLauncher|ForeverHub|ForeverWoW Player)\.exe$'){throw 'This release must not contain a core or game launcher executable'}
    }
    $manifest=@($files|Sort-Object FullName|ForEach-Object {'{0}  {1}'-f(Get-FileHash -LiteralPath $_.FullName).Hash.ToLowerInvariant(),$_.FullName.Substring($root.Length+1).Replace('\','/')})
    [IO.File]::WriteAllLines((Join-Path $root 'SHA256SUMS.txt'),$manifest,(New-Object Text.UTF8Encoding($false)))
}
Write-Manifest $standalone
[void](New-Item -ItemType Directory -Path (Join-Path $repack 'payload') -Force)
Copy-Item -LiteralPath $standalone -Destination (Join-Path $repack 'payload/cms') -Recurse
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'Install-ForeverCMS.ps1') -Destination $repack
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'Install-WebsiteOnly.bat') -Destination (Join-Path $repack 'Install-ForeverCMS.bat')
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'WEBSITE-RELEASE.md') -Destination (Join-Path $repack 'README.md')
Write-Manifest $repack
Add-Type -AssemblyName System.IO.Compression.FileSystem
[IO.Compression.ZipFile]::CreateFromDirectory($standalone,(Join-Path $output $names[0]),[IO.Compression.CompressionLevel]::Optimal,$false)
[IO.Compression.ZipFile]::CreateFromDirectory($repack,(Join-Path $output $names[1]),[IO.Compression.CompressionLevel]::Optimal,$false)
$hashes=@($names[0..1]|ForEach-Object {'{0}  {1}'-f(Get-FileHash -LiteralPath (Join-Path $output $_)).Hash.ToLowerInvariant(),$_})
[IO.File]::WriteAllLines((Join-Path $output $names[2]),$hashes,(New-Object Text.UTF8Encoding($false)))
Write-Output "Packaged committed source $commit. No core, launcher, accounts or private configuration included."
Get-Item -LiteralPath ($names|ForEach-Object {Join-Path $output $_})|Select-Object FullName,Length
Write-Output "Staging: $stage"
