$ErrorActionPreference='Stop'
$extensionDocker='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$extensionNetwork='search-extension-ui-20261006'
function Invoke-ExtensionCleanup {param([string[]]$Arguments) & $extensionDocker @Arguments; if($LASTEXITCODE -ne 0){throw 'Extension environment cleanup failed'}}
$extensionExpected=@('search-extension-ui-mysql-20261006','search-extension-ui-mariadb-20261006','search-extension-ui-app-mysql-20261006','search-extension-ui-app-mariadb-20261006')
$extensionJoined=@(& $extensionDocker network inspect --format '{{range .Containers}}{{println .Name}}{{end}}' $extensionNetwork | Where-Object { $_ })
if($LASTEXITCODE -ne 0 -or @($extensionJoined | Where-Object {$_ -notin $extensionExpected}).Count){throw 'Dedicated network membership mismatch; preserved'}
foreach($extensionEngine in @('mysql','mariadb')){
 $extensionApp="search-extension-ui-app-$extensionEngine-20261006";$extensionDatabase="search-extension-ui-$extensionEngine-20261006"
 $extensionMarker=& $extensionDocker exec $extensionApp php -r 'echo is_file("/tmp/search-extension-web/storage/extension-test-only")&&file_get_contents("/tmp/search-extension-web/storage/extension-test-only")==="isolated-extension-verification"?"OWNED":"INVALID";'
 if($LASTEXITCODE -ne 0 -or $extensionMarker -ne 'OWNED'){throw 'Dedicated application marker mismatch; preserved'}
 $extensionTmpfs=& $extensionDocker inspect --format '{{json .HostConfig.Tmpfs}}' $extensionDatabase
 if($LASTEXITCODE -ne 0 -or $extensionTmpfs -ne '{"/var/lib/mysql":"rw,size=512m"}'){throw 'Dedicated DB tmpfs mismatch; preserved'}
}
foreach($extensionName in $extensionExpected){Invoke-ExtensionCleanup @('rm','-f',$extensionName) | Out-Null}
Invoke-ExtensionCleanup @('network','rm',$extensionNetwork) | Out-Null
Write-Output 'Dedicated extension apps, generated users, temporary login entry, tmpfs databases and network removed.'
