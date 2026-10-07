$ErrorActionPreference='Stop'
$extensionDocker='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$extensionNetwork='search-extension-ui-20261006'
$extensionWorkspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
function Invoke-ExtensionCleanup {param([string[]]$Arguments) & $extensionDocker @Arguments; if($LASTEXITCODE -ne 0){throw 'Extension environment cleanup failed'}}
$extensionExpected=@('search-extension-ui-mysql-20261006','search-extension-ui-mariadb-20261006','search-extension-ui-app-mysql-20261006','search-extension-ui-app-mariadb-20261006')
$extensionJoined=@(& $extensionDocker network inspect --format '{{range .Containers}}{{println .Name}}{{end}}' $extensionNetwork | Where-Object { $_ })
if($LASTEXITCODE -ne 0 -or @($extensionJoined | Where-Object {$_ -notin $extensionExpected}).Count){throw 'Dedicated network membership mismatch; preserved'}
foreach($extensionEngine in @('mysql','mariadb')){
 $extensionApp="search-extension-ui-app-$extensionEngine-20261006";$extensionDatabase="search-extension-ui-$extensionEngine-20261006"
 # A stopped container cannot exec. Copy only the nonsensitive ownership marker, never config/env.
 $extensionProof=Join-Path $extensionWorkspace ('.test-output/extension-cleanup-marker-'+[Guid]::NewGuid().ToString('N'))
 if(Test-Path -LiteralPath $extensionProof){throw 'Fresh marker proof unavailable'}
 try{
  Invoke-ExtensionCleanup @('cp',"${extensionApp}:/tmp/search-extension-web/storage/extension-test-only",$extensionProof) | Out-Null
  if([IO.File]::ReadAllText($extensionProof) -ne 'isolated-extension-verification'){throw 'Dedicated application marker mismatch; preserved'}
 }finally{if(Test-Path -LiteralPath $extensionProof){Remove-Item -LiteralPath $extensionProof}}
 foreach($extensionName in @($extensionApp,$extensionDatabase)){
  $extensionMode=& $extensionDocker inspect --format '{{.HostConfig.NetworkMode}}' $extensionName
  if($LASTEXITCODE -ne 0 -or $extensionMode -ne $extensionNetwork){throw 'Dedicated container network mismatch; preserved'}
 }
 $extensionTmpfs=& $extensionDocker inspect --format '{{json .HostConfig.Tmpfs}}' $extensionDatabase
 if($LASTEXITCODE -ne 0 -or $extensionTmpfs -ne '{"/var/lib/mysql":"rw,size=512m"}'){throw 'Dedicated DB tmpfs mismatch; preserved'}
}
foreach($extensionName in $extensionExpected){Invoke-ExtensionCleanup @('rm','-f',$extensionName) | Out-Null}
Invoke-ExtensionCleanup @('network','rm',$extensionNetwork) | Out-Null
Write-Output 'Dedicated extension apps, generated users, temporary login entry, tmpfs databases and network removed.'
