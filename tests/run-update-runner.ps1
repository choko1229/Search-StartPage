param([ValidateRange(1,3)][int]$Rounds=3)
$ErrorActionPreference='Stop'
$runnerDocker='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$runnerWorkspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$runnerNetwork='search-runner-alias-20261005'
$runnerApps=@();$runnerDatabases=@();$runnerNetworkCreated=$false
function Invoke-RunnerDocker {
 param([string[]]$Arguments)
 & $runnerDocker @Arguments
 if($LASTEXITCODE -ne 0){throw 'Isolated update runner verification failed'}
}
try {
 $runnerExisting=& $runnerDocker network ls --filter "name=^$runnerNetwork`$" --format '{{.Name}}'
 if($LASTEXITCODE -ne 0 -or $runnerExisting){throw 'Dedicated network unavailable or already exists'}
 Invoke-RunnerDocker @('network','create','--internal',$runnerNetwork) | Out-Null
 $runnerNetworkCreated=$true
 foreach($runnerEngine in @('mysql','mariadb')) {
  # These exact hosts are allowlisted by the PHP test; refuse any existing container.
  $runnerDatabase="search-update-backup-$runnerEngine-20261004"
  $runnerExisting=& $runnerDocker ps -a --filter "name=^/$runnerDatabase`$" --format '{{.Names}}'
  if($LASTEXITCODE -ne 0 -or $runnerExisting){throw 'Dedicated database already exists or cannot be inspected'}
  $runnerPassword=[Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N')
  $runnerPrefix=if($runnerEngine -eq 'mysql'){'MYSQL'}else{'MARIADB'}
  $runnerImage=if($runnerEngine -eq 'mysql'){'mysql:8.0'}else{'mariadb:10.11'}
  Invoke-RunnerDocker @('run','-d','--name',$runnerDatabase,'--network',$runnerNetwork,'--tmpfs','/var/lib/mysql:rw,size=512m','-e',"${runnerPrefix}_DATABASE=update_backup",'-e',"${runnerPrefix}_USER=backup",'-e',"${runnerPrefix}_PASSWORD=$runnerPassword",'-e',"${runnerPrefix}_RANDOM_ROOT_PASSWORD=yes",$runnerImage) | Out-Null
  $runnerDatabases+=$runnerDatabase
  foreach($runnerRound in 1..$Rounds) {
   $runnerApp="search-runner-alias-$runnerEngine-$runnerRound-20261005"
   $runnerRoot='/tmp/search-update-runner-source'
   $runnerExisting=& $runnerDocker ps -a --filter "name=^/$runnerApp`$" --format '{{.Names}}'
   if($LASTEXITCODE -ne 0 -or $runnerExisting){throw 'Dedicated app already exists or cannot be inspected'}
   Invoke-RunnerDocker @('run','-d','--name',$runnerApp,'--network',$runnerNetwork,'--user','www-data','--cap-drop','ALL','--security-opt','no-new-privileges','-e','SEARCH_TEST_MODE=1','-e',"TEST_BACKUP_HOST=$runnerDatabase",'-e',"TEST_BACKUP_PASSWORD=$runnerPassword",'search-update-fpm-engine:20261004','sleep','infinity') | Out-Null
   $runnerApps+=$runnerApp
   Invoke-RunnerDocker @('exec',$runnerApp,'mkdir','-p',"$runnerRoot/config")
   foreach($runnerPart in @('app','bin','database','tests','lang','public')){Invoke-RunnerDocker @('cp',"$runnerWorkspace/$runnerPart","${runnerApp}:$runnerRoot/")}
   foreach($runnerPart in @('config/config.example.php','config/providers.php','VERSION')){Invoke-RunnerDocker @('cp',"$runnerWorkspace/$runnerPart","${runnerApp}:$runnerRoot/$runnerPart")}
   Invoke-RunnerDocker @('exec',$runnerApp,'php','-l',"$runnerRoot/tests/update-runner.php")
   Invoke-RunnerDocker @('exec',$runnerApp,'php','-l',"$runnerRoot/app/Services/UpdateRunner.php")
   Invoke-RunnerDocker @('exec',$runnerApp,'php','-d','display_errors=stderr','-d','log_errors=0',"$runnerRoot/tests/update-runner.php")
   Invoke-RunnerDocker @('exec',$runnerApp,'php',"$runnerRoot/tests/run.php")
   Invoke-RunnerDocker @('exec',$runnerApp,'php',"$runnerRoot/tests/update-package.php")
   Write-Output "Runner $runnerEngine round $runnerRound completed."
  }
 }
}finally{
 foreach($runnerApp in $runnerApps){Invoke-RunnerDocker @('rm','-f',$runnerApp) | Out-Null}
 foreach($runnerDatabase in $runnerDatabases){
  $runnerTmpfs=& $runnerDocker inspect --format '{{json .HostConfig.Tmpfs}}' $runnerDatabase
  if($LASTEXITCODE -ne 0 -or $runnerTmpfs -ne '{"/var/lib/mysql":"rw,size=512m"}'){throw 'Dedicated database tmpfs mismatch; preserved'}
  Invoke-RunnerDocker @('rm','-f',$runnerDatabase) | Out-Null
 }
 if($runnerNetworkCreated){Invoke-RunnerDocker @('network','rm',$runnerNetwork) | Out-Null}
}
