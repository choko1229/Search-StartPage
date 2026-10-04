$ErrorActionPreference='Stop'
$dockerSyncUi='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$workspaceSyncUi=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
function Invoke-SyncUiDocker { param([string[]]$Arguments) & $dockerSyncUi @Arguments; if($LASTEXITCODE -ne 0){throw 'Isolated sync UI setup failed'} }
$appsSyncUi=@();$dbsSyncUi=@();$readySyncUi=$false
try{
 foreach($engineSyncUi in @('mysql','mariadb')){
  $databaseSyncUi="search-sync-ui-$engineSyncUi-20261004";$appSyncUi="search-sync-ui-app-$engineSyncUi-20261004";$portSyncUi=if($engineSyncUi -eq 'mysql'){8111}else{8112}
  foreach($nameSyncUi in @($databaseSyncUi,$appSyncUi)){$existingSyncUi=& $dockerSyncUi ps -a --filter "name=^/$nameSyncUi`$" --format '{{.Names}}';if($existingSyncUi){throw 'Dedicated sync UI name already exists'}}
  $passwordSyncUi=[Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N');$prefixSyncUi=if($engineSyncUi -eq 'mysql'){'MYSQL'}else{'MARIADB'};$imageSyncUi=if($engineSyncUi -eq 'mysql'){'mysql:8.0'}else{'mariadb:10.11'}
  Invoke-SyncUiDocker @('run','-d','--name',$databaseSyncUi,'--network','search-phase9-roles-20261004_default','--tmpfs','/var/lib/mysql:rw,size=512m','-e',"${prefixSyncUi}_DATABASE=sync_ui",'-e',"${prefixSyncUi}_USER=sync",'-e',"${prefixSyncUi}_PASSWORD=$passwordSyncUi",'-e',"${prefixSyncUi}_RANDOM_ROOT_PASSWORD=yes",$imageSyncUi) | Out-Null
  $dbsSyncUi+=$databaseSyncUi
  Invoke-SyncUiDocker @('run','-d','--name',$appSyncUi,'--network','search-phase9-roles-20261004_default','--tmpfs','/var/www/app/config:rw,size=4m','--tmpfs','/var/www/app/storage:rw,size=64m','-p',"127.0.0.1:${portSyncUi}:80",'-e','SEARCH_TEST_MODE=1','-e','SEARCH_LOCAL_DEVELOPMENT=1','-e',"TEST_SYNC_UI_HOST=$databaseSyncUi",'-e',"TEST_SYNC_UI_PASSWORD=$passwordSyncUi",'search-phase9-roles-20261004-app-mysql') | Out-Null
  $appsSyncUi+=$appSyncUi
  foreach($partSyncUi in @('app','public','lang','database','tests','bin')){Invoke-SyncUiDocker @('cp',"$workspaceSyncUi/$partSyncUi","${appSyncUi}:/var/www/app/")}
  foreach($partSyncUi in @('config.example.php','providers.php')){Invoke-SyncUiDocker @('cp',"$workspaceSyncUi/config/$partSyncUi","${appSyncUi}:/tmp/$partSyncUi");Invoke-SyncUiDocker @('exec',$appSyncUi,'cp',"/tmp/$partSyncUi","/var/www/app/config/$partSyncUi")}
  Invoke-SyncUiDocker @('cp',"$workspaceSyncUi/VERSION","${appSyncUi}:/var/www/app/VERSION")
  Invoke-SyncUiDocker @('exec',$appSyncUi,'touch','/var/www/app/storage/sync-ui-test-only')
  Invoke-SyncUiDocker @('exec',$appSyncUi,'php','-l','/var/www/app/tests/admin-sync-ui-development.php')
  Invoke-SyncUiDocker @('exec',$appSyncUi,'php','/var/www/app/tests/admin-sync-ui-development.php','setup')
  Invoke-SyncUiDocker @('exec',$appSyncUi,'chown','-R','www-data:www-data','/var/www/app/config','/var/www/app/storage')
  Invoke-SyncUiDocker @('exec',$appSyncUi,'chmod','700','/var/www/app/config','/var/www/app/storage')
  foreach($roundSyncUi in 1..3){Invoke-SyncUiDocker @('exec','-u','www-data',$appSyncUi,'php','/var/www/app/tests/sync.php');Invoke-SyncUiDocker @('exec','-u','www-data',$appSyncUi,'php','/var/www/app/tests/run.php');Write-Output "Sync UI $engineSyncUi regression round $roundSyncUi completed."}
  Invoke-SyncUiDocker @('exec',$appSyncUi,'php','/var/www/app/tests/admin-sync-ui-development.php','seed')
  Invoke-SyncUiDocker @('exec',$appSyncUi,'chown','-R','www-data:www-data','/var/www/app/public/_test')
  Invoke-SyncUiDocker @('exec','-u','www-data',$appSyncUi,'php','/var/www/app/tests/admin-sync-ui-development.php','observe')
 }
 $readySyncUi=$true;Write-Output 'Isolated sync UI ready on loopback ports 8111 and 8112; independent device browser verification is pending.'
}finally{
 if(!$readySyncUi){foreach($appSyncUi in $appsSyncUi){Invoke-SyncUiDocker @('rm','-f',$appSyncUi) | Out-Null};foreach($databaseSyncUi in $dbsSyncUi){$tmpfsSyncUi=& $dockerSyncUi inspect --format '{{json .HostConfig.Tmpfs}}' $databaseSyncUi;if($LASTEXITCODE -ne 0 -or $tmpfsSyncUi -ne '{"/var/lib/mysql":"rw,size=512m"}'){throw 'Dedicated role DB tmpfs mismatch; preserved'};Invoke-SyncUiDocker @('rm','-f',$databaseSyncUi) | Out-Null}}
}
