param([ValidateRange(1,3)][int]$Rounds=3,[switch]$Maintenance,[switch]$MigrationFailure,[switch]$MultipleMasters)
$ErrorActionPreference='Stop'
$dockerFpmEngine='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$workspaceFpmEngine=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$maintenanceFpmEngine=if($Maintenance){'1'}else{'0'}
$failureFpmEngine=if($MigrationFailure){'1'}else{'0'}
$multipleFpmEngine=if($MultipleMasters){'1'}else{'0'}
if($MigrationFailure -and !$Maintenance){throw 'Migration failure check requires maintenance mode'}
function Invoke-FpmEngineDocker { param([string[]]$Arguments) & $dockerFpmEngine @Arguments; if($LASTEXITCODE -ne 0){throw 'Isolated FPM engine check failed'} }
$createdFpmEngine=@();$dbFpmEngine=@()
try {
 foreach($engineFpmEngine in @('mysql','mariadb')){
  $databaseFpmEngine="search-update-backup-$engineFpmEngine-20261004"
  $existingFpmEngine=& $dockerFpmEngine ps -a --filter "name=^/$databaseFpmEngine`$" --format '{{.Names}}';if($existingFpmEngine){throw 'Dedicated DB name already exists'}
  $passwordFpmEngine=[Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N')
  $prefixFpmEngine=if($engineFpmEngine -eq 'mysql'){'MYSQL'}else{'MARIADB'};$imageFpmEngine=if($engineFpmEngine -eq 'mysql'){'mysql:8.0'}else{'mariadb:10.11'}
  Invoke-FpmEngineDocker @('run','-d','--name',$databaseFpmEngine,'--network','search-phase9-roles-20261004_default','--tmpfs','/var/lib/mysql:rw,size=512m','-e',"${prefixFpmEngine}_DATABASE=update_backup",'-e',"${prefixFpmEngine}_USER=backup",'-e',"${prefixFpmEngine}_PASSWORD=$passwordFpmEngine",'-e',"${prefixFpmEngine}_RANDOM_ROOT_PASSWORD=yes",$imageFpmEngine) | Out-Null
  $dbFpmEngine+=$databaseFpmEngine
  foreach($roundFpmEngine in 1..$Rounds){
   $appFpmEngine="search-fpm-engine-$engineFpmEngine-$roundFpmEngine-20261004";$rootFpmEngine='/tmp/search-update-fpm-source'
   $existingFpmEngine=& $dockerFpmEngine ps -a --filter "name=^/$appFpmEngine`$" --format '{{.Names}}';if($existingFpmEngine){throw 'Dedicated app name already exists'}
   Invoke-FpmEngineDocker @('run','-d','--name',$appFpmEngine,'--network','search-phase9-roles-20261004_default','-e','SEARCH_TEST_MODE=1','-e','TEST_UPDATE_FPM=1','-e',"TEST_UPDATE_MULTIPLE_MASTERS=$multipleFpmEngine",'-e',"TEST_UPDATE_MAINTENANCE=$maintenanceFpmEngine",'-e',"TEST_UPDATE_MIGRATION_FAILURE=$failureFpmEngine",'-e',"TEST_BACKUP_HOST=$databaseFpmEngine",'-e',"TEST_BACKUP_PASSWORD=$passwordFpmEngine",'search-update-fpm-engine:20261004','sleep','infinity') | Out-Null
   $createdFpmEngine+=$appFpmEngine
   Invoke-FpmEngineDocker @('exec',$appFpmEngine,'mkdir','-p',"$rootFpmEngine/config","$rootFpmEngine/storage")
   foreach($partFpmEngine in @('app','bin','database','tests','lang','public')){Invoke-FpmEngineDocker @('cp',"$workspaceFpmEngine/$partFpmEngine","${appFpmEngine}:$rootFpmEngine/")}
   foreach($partFpmEngine in @('config/config.example.php','config/providers.php','VERSION')){Invoke-FpmEngineDocker @('cp',"$workspaceFpmEngine/$partFpmEngine","${appFpmEngine}:$rootFpmEngine/$partFpmEngine")}
   Invoke-FpmEngineDocker @('cp',"$workspaceFpmEngine/tests/fixtures/update-fpm/nginx.conf","${appFpmEngine}:/tmp/update-fpm-nginx.conf")
   Invoke-FpmEngineDocker @('exec',$appFpmEngine,'touch',"$rootFpmEngine/storage/web-cache-test-only")
   Invoke-FpmEngineDocker @('exec',$appFpmEngine,'chown','-R','www-data:www-data',$rootFpmEngine)
   Invoke-FpmEngineDocker @('exec',$appFpmEngine,'php-fpm','-D')
   if($MultipleMasters){
    Invoke-FpmEngineDocker @('cp',"$workspaceFpmEngine/tests/fixtures/update-fpm/second-master.conf","${appFpmEngine}:/tmp/update-fpm-second-master.conf")
    Invoke-FpmEngineDocker @('exec',$appFpmEngine,'php-fpm','-D','-y','/tmp/update-fpm-second-master.conf')
    Invoke-FpmEngineDocker @('exec',$appFpmEngine,'php',"$rootFpmEngine/tests/update-fpm-multiple-setup.php")
   }
   Invoke-FpmEngineDocker @('exec',$appFpmEngine,'nginx','-c','/tmp/update-fpm-nginx.conf')
   Invoke-FpmEngineDocker @('exec','-u','www-data',$appFpmEngine,'php','-l',"$rootFpmEngine/tests/update-requests-http.php")
   Invoke-FpmEngineDocker @('exec','-u','www-data',$appFpmEngine,'php','-l',"$rootFpmEngine/tests/update-fpm-runtime.php")
   Invoke-FpmEngineDocker @('exec','-u','www-data',$appFpmEngine,'php','-d','display_errors=stderr','-d','log_errors=0',"$rootFpmEngine/tests/update-requests-http.php")
   Invoke-FpmEngineDocker @('exec','-u','www-data',$appFpmEngine,'php',"$rootFpmEngine/tests/run.php")
   if($roundFpmEngine -eq $Rounds){Invoke-FpmEngineDocker @('exec','-u','www-data','-e','TEST_UPDATE_FPM=0','-e','TEST_UPDATE_MULTIPLE_MASTERS=0',$appFpmEngine,'php','-d','display_errors=stderr','-d','log_errors=0',"$rootFpmEngine/tests/update-requests-http.php")}
   Write-Output "FPM Engine $engineFpmEngine round $roundFpmEngine completed."
  }
 }
}finally{
 foreach($appFpmEngine in $createdFpmEngine){Invoke-FpmEngineDocker @('rm','-f',$appFpmEngine) | Out-Null}
 foreach($databaseFpmEngine in $dbFpmEngine){$tmpfsFpmEngine=& $dockerFpmEngine inspect --format '{{json .HostConfig.Tmpfs}}' $databaseFpmEngine;if($LASTEXITCODE -ne 0 -or $tmpfsFpmEngine -ne '{"/var/lib/mysql":"rw,size=512m"}'){throw 'Dedicated DB tmpfs mismatch; preserved'};Invoke-FpmEngineDocker @('rm','-f',$databaseFpmEngine) | Out-Null}
}
