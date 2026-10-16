param(
    [Parameter(Mandatory=$true)][string]$Destination,
    [string]$PhpRuntime
)
# Builds an unconfigured preview directory, not an archive or a live deployment.
$ErrorActionPreference='Stop'
$sourceRoot=[IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$targetRoot=[IO.Path]::GetFullPath($Destination)
if(Test-Path -LiteralPath $targetRoot){throw 'Choose a new, empty destination name. Existing folders are never overwritten.'}
if($targetRoot.StartsWith($sourceRoot+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Stage outside the source repository.'}
$files=@(& git -C $sourceRoot ls-files --cached --others --exclude-standard)
if($LASTEXITCODE-ne 0 -or $files.Count-lt 10){throw 'Cannot read the source repository file list.'}
$blocked='(?i)(^|/)(\.git|logs|cache|tmp|sessions|backups|uploads|payload|releases|dist)(/|$)|(^|/)(config\.(?:secret|database)\.php|\.env(?:\..*)?)$|\.(?:log|pid|pem|pfx|p12|key|zip|dmp|exe|dll)$|\.secret\.php$'
$schemaFiles=@('sql/forever_site.sql','sql/forever_armory_library.sql','sql/forever_creator_world.sql','sql/forever_premium_shop.sql','sql/forever_shared_shop.sql')
$selected=@($files|Sort-Object -Unique|Where-Object {$_-notmatch $blocked -and ($_-notmatch '(?i)\.(sql|db|sqlite|sqlite3|bak|gz|7z|rar)$' -or $schemaFiles-contains $_) -and (Test-Path -LiteralPath (Join-Path $sourceRoot $_) -PathType Leaf)})
foreach($relative in $selected){
    $source=Get-Item -LiteralPath (Join-Path $sourceRoot $relative)
    if($source.Attributes-band [IO.FileAttributes]::ReparsePoint){throw "Linked source not allowed: $relative"}
    if($relative-match '(^|/)config[^/]*\.php$|includes/join-guide/'){
        if((Get-Content -LiteralPath $source.FullName -Raw)-match '(?i)foreverwow\.servegame\.com|filinodz@|[A-Z]:[\\/]WoWForever'){throw "Local deployment content in $relative; review before staging."}
    }
}
if($PhpRuntime){
    $runtimeRoot=[IO.Path]::GetFullPath($PhpRuntime)
    foreach($required in @('php.exe','php.ini','license.txt')){if(-not(Test-Path -LiteralPath (Join-Path $runtimeRoot $required) -PathType Leaf)){throw "PHP runtime missing $required"}}
    $runtimeFiles=@(Get-ChildItem -LiteralPath $runtimeRoot -File -Recurse)
    if(Get-ChildItem -LiteralPath $runtimeRoot -Recurse -Force | Where-Object {$_.Attributes-band [IO.FileAttributes]::ReparsePoint}){throw 'Linked PHP runtime files are not allowed.'}
}
[void](New-Item -ItemType Directory -Path $targetRoot)
foreach($relative in $selected){
    $target=Join-Path $targetRoot $relative
    [void](New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force)
    Copy-Item -LiteralPath (Join-Path $sourceRoot $relative) -Destination $target
}
if($PhpRuntime){
    foreach($file in $runtimeFiles){
        $relative=$file.FullName.Substring($runtimeRoot.TrimEnd('\','/').Length+1)
        # Vendor binaries, extensions and their license/readme files only.
        if($relative-match '(?i)(^|[\\/])(logs|tmp|sessions|cache)([\\/]|$)|\.(log|pid|pem|pfx|key)$'){continue}
        $target=Join-Path (Join-Path $targetRoot 'php') $relative
        [void](New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force)
        Copy-Item -LiteralPath $file.FullName -Destination $target
    }
}
foreach($directory in @('logs','tmp/sessions')){[void](New-Item -ItemType Directory -Path (Join-Path $targetRoot $directory) -Force)}
$staged=Get-ChildItem -LiteralPath $targetRoot -File -Recurse -Force
if($staged|Where-Object {$_.Name-match '(?i)\.secret\.php$|^config\.database\.php$|^\.env|\.(pem|pfx|p12|key|log|pid|dmp)$'}){throw 'Private/runtime file detected. Do not distribute this preview.'}
$manifest=@($staged|Sort-Object FullName|ForEach-Object {
    '{0}  {1}'-f (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant(),$_.FullName.Substring($targetRoot.Length+1).Replace('\','/')
})
[IO.File]::WriteAllLines((Join-Path $targetRoot 'SHA256SUMS.txt'),$manifest,(New-Object Text.UTF8Encoding($false)))
Write-Output "Standalone preview ready: $targetRoot ($($staged.Count) files). No ZIP or publication was created."
