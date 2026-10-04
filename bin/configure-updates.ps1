param(
 [string]$Container='search-phase9-roles-20261004-app-mysql-1',
 [string]$Repository='choko1229/Search-StartPage'
)
$ErrorActionPreference='Stop'
if($Container -notmatch '^[A-Za-z0-9][A-Za-z0-9_.-]*$' -or $Repository -notmatch '^[A-Za-z0-9][A-Za-z0-9-]{0,38}/[A-Za-z0-9][A-Za-z0-9_.-]{0,99}$'){throw 'Invalid target'}
$updatesDocker=Join-Path $env:LOCALAPPDATA 'Programs/DockerDesktop/resources/bin/docker.exe'
if(!(Test-Path -LiteralPath $updatesDocker)){throw 'Docker CLI unavailable'}
# Only the public helper is copied; the token travels on stdin, never in arguments.
$updatesHelper='/var/www/app/bin/configure-updates.php'
$updatesSecret=Read-Host 'GitHub Token (hidden; empty for a public repository)' -AsSecureString
$updatesPointer=[IntPtr]::Zero
try{
 & $updatesDocker cp (Join-Path $PSScriptRoot 'configure-updates.php') "${Container}:$updatesHelper"
 if($LASTEXITCODE -ne 0){throw 'Helper installation failed'}
 $updatesPointer=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($updatesSecret)
 $updatesPlain=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($updatesPointer)
 $updatesPayload=@{repository=$Repository;token=$updatesPlain} | ConvertTo-Json -Compress
 $updatesPayload | & $updatesDocker exec --user www-data -i $Container php $updatesHelper
 if($LASTEXITCODE -ne 0){throw 'Update configuration failed. No credentials were printed.'}
}finally{
 if($updatesPointer -ne [IntPtr]::Zero){[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($updatesPointer)}
 $updatesPlain=$null;$updatesPayload=$null;$updatesSecret.Dispose()
}
Write-Host 'Saved inside the selected container. The repository stays private; no update was applied.'
