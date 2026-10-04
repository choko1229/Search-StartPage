param([ValidateRange(1,3)][int]$Rounds=3)
$ErrorActionPreference='Stop'
$dockerPresetOutage='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$workspacePresetOutage=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
function Invoke-PresetOutageDocker {param([string[]]$Arguments) & $dockerPresetOutage @Arguments; if($LASTEXITCODE -ne 0){throw 'Isolated preset outage check failed'}}
function Confirm-PresetOutageMount {
 param([string]$Container,[string]$Volume)
 $mountsPresetOutage=& $dockerPresetOutage inspect --format '{{json .Mounts}}' $Container
 if($LASTEXITCODE -ne 0){throw 'Dedicated DB inspection failed; preserved'}
 $matchesPresetOutage=@(($mountsPresetOutage | ConvertFrom-Json) | Where-Object {$_.Type -eq 'volume' -and $_.Name -eq $Volume -and $_.Destination -eq '/var/lib/mysql'})
 if($matchesPresetOutage.Count -ne 1){throw 'Dedicated DB volume mismatch; preserved'}
}
foreach($enginePresetOutage in @('mysql','mariadb')){foreach($roundPresetOutage in 1..$Rounds){
 $databasePresetOutage="search-preset-outage-$enginePresetOutage-$roundPresetOutage-20261004"
 $appPresetOutage="search-preset-outage-app-$enginePresetOutage-$roundPresetOutage-20261004"
 $volumePresetOutage="$databasePresetOutage-data"
 $createdAppPresetOutage=$false;$createdDbPresetOutage=$false;$createdVolumePresetOutage=$false
 try{
  foreach($namePresetOutage in @($databasePresetOutage,$appPresetOutage)){
   $existingPresetOutage=& $dockerPresetOutage ps -a --filter "name=^/$namePresetOutage`$" --format '{{.Names}}'
   if($LASTEXITCODE -ne 0 -or $existingPresetOutage){throw 'Dedicated name unavailable; preserved'}
  }
  $existingVolumePresetOutage=& $dockerPresetOutage volume ls --filter "name=^$volumePresetOutage`$" --format '{{.Name}}'
  if($LASTEXITCODE -ne 0 -or $existingVolumePresetOutage){throw 'Dedicated volume name unavailable; preserved'}
  Invoke-PresetOutageDocker @('volume','create','--label','search.preset_outage=20261004',$volumePresetOutage) | Out-Null
  $createdVolumePresetOutage=$true
  $passwordPresetOutage=[Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N')
  $prefixPresetOutage=if($enginePresetOutage -eq 'mysql'){'MYSQL'}else{'MARIADB'}
  $imagePresetOutage=if($enginePresetOutage -eq 'mysql'){'mysql:8.0'}else{'mariadb:10.11'}
  Invoke-PresetOutageDocker @('run','-d','--name',$databasePresetOutage,'--network','search-phase9-roles-20261004_default','--mount',"type=volume,source=$volumePresetOutage,target=/var/lib/mysql",'-e',"${prefixPresetOutage}_DATABASE=preset_outage",'-e',"${prefixPresetOutage}_USER=preset",'-e',"${prefixPresetOutage}_PASSWORD=$passwordPresetOutage",'-e',"${prefixPresetOutage}_RANDOM_ROOT_PASSWORD=yes",$imagePresetOutage) | Out-Null
  $createdDbPresetOutage=$true
  Invoke-PresetOutageDocker @('run','-d','--name',$appPresetOutage,'--network','search-phase9-roles-20261004_default','-e','SEARCH_TEST_MODE=1','-e','SEARCH_LOCAL_DEVELOPMENT=1','-e',"TEST_PRESET_OUTAGE_HOST=$databasePresetOutage",'-e',"TEST_PRESET_OUTAGE_PASSWORD=$passwordPresetOutage",'search-phase9-roles-20261004-app-mysql','sleep','infinity') | Out-Null
  $createdAppPresetOutage=$true;$rootPresetOutage='/tmp/search-preset-outage-source'
  Invoke-PresetOutageDocker @('exec',$appPresetOutage,'mkdir','-p',"$rootPresetOutage/config","$rootPresetOutage/storage")
  foreach($partPresetOutage in @('app','database','tests','lang','public')){Invoke-PresetOutageDocker @('cp',"$workspacePresetOutage/$partPresetOutage","${appPresetOutage}:$rootPresetOutage/")}
  foreach($partPresetOutage in @('config/config.example.php','config/providers.php','VERSION')){Invoke-PresetOutageDocker @('cp',"$workspacePresetOutage/$partPresetOutage","${appPresetOutage}:$rootPresetOutage/$partPresetOutage")}
  Invoke-PresetOutageDocker @('exec',$appPresetOutage,'touch',"$rootPresetOutage/storage/preset-outage-test-only")
  Invoke-PresetOutageDocker @('exec',$appPresetOutage,'php','-l',"$rootPresetOutage/tests/preset-outage.php")
  Invoke-PresetOutageDocker @('exec',$appPresetOutage,'php',"$rootPresetOutage/tests/preset-outage.php",'prepare')
  Confirm-PresetOutageMount $databasePresetOutage $volumePresetOutage
  Invoke-PresetOutageDocker @('stop',$databasePresetOutage) | Out-Null
  Invoke-PresetOutageDocker @('exec',$appPresetOutage,'php',"$rootPresetOutage/tests/preset-outage.php",'outage')
  Invoke-PresetOutageDocker @('start',$databasePresetOutage) | Out-Null
  Invoke-PresetOutageDocker @('exec',$appPresetOutage,'php',"$rootPresetOutage/tests/preset-outage.php",'recovered')
  Invoke-PresetOutageDocker @('exec',$appPresetOutage,'php',"$rootPresetOutage/tests/run.php")
  Write-Output "Preset outage $enginePresetOutage round $roundPresetOutage completed."
 }finally{
  if($createdAppPresetOutage){Invoke-PresetOutageDocker @('rm','-f',$appPresetOutage) | Out-Null}
  if($createdDbPresetOutage){Confirm-PresetOutageMount $databasePresetOutage $volumePresetOutage;Invoke-PresetOutageDocker @('rm','-f',$databasePresetOutage) | Out-Null}
  if($createdVolumePresetOutage){
   $labelPresetOutage=& $dockerPresetOutage volume inspect --format '{{index .Labels "search.preset_outage"}}' $volumePresetOutage
   if($LASTEXITCODE -ne 0 -or $labelPresetOutage -ne '20261004'){throw 'Dedicated volume label mismatch; preserved'}
   Invoke-PresetOutageDocker @('volume','rm',$volumePresetOutage) | Out-Null
  }
 }
}}
