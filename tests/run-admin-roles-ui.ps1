$ErrorActionPreference='Stop'
$dockerRolesUi='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$workspaceRolesUi=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
function Invoke-RolesUiDocker { param([string[]]$Arguments) & $dockerRolesUi @Arguments; if($LASTEXITCODE -ne 0){throw 'Isolated roles UI setup failed'} }
$appsRolesUi=@();$dbsRolesUi=@();$readyRolesUi=$false
try{
 foreach($engineRolesUi in @('mysql','mariadb')){
  $databaseRolesUi="search-roles-ui-$engineRolesUi-20261004";$appRolesUi="search-roles-ui-app-$engineRolesUi-20261004";$portRolesUi=if($engineRolesUi -eq 'mysql'){8109}else{8110}
  foreach($nameRolesUi in @($databaseRolesUi,$appRolesUi)){$existingRolesUi=& $dockerRolesUi ps -a --filter "name=^/$nameRolesUi`$" --format '{{.Names}}';if($existingRolesUi){throw 'Dedicated role UI name already exists'}}
  $passwordRolesUi=[Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N');$prefixRolesUi=if($engineRolesUi -eq 'mysql'){'MYSQL'}else{'MARIADB'};$imageRolesUi=if($engineRolesUi -eq 'mysql'){'mysql:8.0'}else{'mariadb:10.11'}
  Invoke-RolesUiDocker @('run','-d','--name',$databaseRolesUi,'--network','search-phase9-roles-20261004_default','--tmpfs','/var/lib/mysql:rw,size=512m','-e',"${prefixRolesUi}_DATABASE=roles_ui",'-e',"${prefixRolesUi}_USER=roles",'-e',"${prefixRolesUi}_PASSWORD=$passwordRolesUi",'-e',"${prefixRolesUi}_RANDOM_ROOT_PASSWORD=yes",$imageRolesUi) | Out-Null
  $dbsRolesUi+=$databaseRolesUi
  Invoke-RolesUiDocker @('run','-d','--name',$appRolesUi,'--network','search-phase9-roles-20261004_default','--tmpfs','/var/www/app/config:rw,size=4m','--tmpfs','/var/www/app/storage:rw,size=64m','-p',"127.0.0.1:${portRolesUi}:80",'-e','SEARCH_TEST_MODE=1','-e','SEARCH_LOCAL_DEVELOPMENT=1','-e',"TEST_ROLES_UI_HOST=$databaseRolesUi",'-e',"TEST_ROLES_UI_PASSWORD=$passwordRolesUi",'search-phase9-roles-20261004-app-mysql') | Out-Null
  $appsRolesUi+=$appRolesUi
  foreach($partRolesUi in @('app','public','lang','database','tests','bin')){Invoke-RolesUiDocker @('cp',"$workspaceRolesUi/$partRolesUi","${appRolesUi}:/var/www/app/")}
  foreach($partRolesUi in @('config.example.php','providers.php')){Invoke-RolesUiDocker @('cp',"$workspaceRolesUi/config/$partRolesUi","${appRolesUi}:/tmp/$partRolesUi");Invoke-RolesUiDocker @('exec',$appRolesUi,'cp',"/tmp/$partRolesUi","/var/www/app/config/$partRolesUi")}
  Invoke-RolesUiDocker @('cp',"$workspaceRolesUi/VERSION","${appRolesUi}:/var/www/app/VERSION")
  Invoke-RolesUiDocker @('exec',$appRolesUi,'touch','/var/www/app/storage/roles-ui-test-only')
  Invoke-RolesUiDocker @('exec',$appRolesUi,'php','-l','/var/www/app/tests/admin-roles-ui-development.php')
  Invoke-RolesUiDocker @('exec',$appRolesUi,'php','/var/www/app/tests/admin-roles-ui-development.php','setup')
  Invoke-RolesUiDocker @('exec',$appRolesUi,'chown','-R','www-data:www-data','/var/www/app/config','/var/www/app/storage')
  Invoke-RolesUiDocker @('exec',$appRolesUi,'chmod','700','/var/www/app/config','/var/www/app/storage')
  foreach($roundRolesUi in 1..3){Invoke-RolesUiDocker @('exec','-u','www-data',$appRolesUi,'php','/var/www/app/tests/admin-roles.php');Invoke-RolesUiDocker @('exec','-u','www-data',$appRolesUi,'php','/var/www/app/tests/run.php');Write-Output "Role UI $engineRolesUi regression round $roundRolesUi completed."}
  Invoke-RolesUiDocker @('exec',$appRolesUi,'php','/var/www/app/tests/admin-roles-ui-development.php','seed')
  Invoke-RolesUiDocker @('exec',$appRolesUi,'chown','-R','www-data:www-data','/var/www/app/public/_test')
  Invoke-RolesUiDocker @('exec','-u','www-data',$appRolesUi,'php','/var/www/app/tests/admin-roles-ui-development.php','observe')
 }
 $readyRolesUi=$true;Write-Output 'Isolated role UI ready on loopback ports 8109 and 8110; browser role grant is pending confirmation.'
}finally{
 if(!$readyRolesUi){foreach($appRolesUi in $appsRolesUi){Invoke-RolesUiDocker @('rm','-f',$appRolesUi) | Out-Null};foreach($databaseRolesUi in $dbsRolesUi){$tmpfsRolesUi=& $dockerRolesUi inspect --format '{{json .HostConfig.Tmpfs}}' $databaseRolesUi;if($LASTEXITCODE -ne 0 -or $tmpfsRolesUi -ne '{"/var/lib/mysql":"rw,size=512m"}'){throw 'Dedicated role DB tmpfs mismatch; preserved'};Invoke-RolesUiDocker @('rm','-f',$databaseRolesUi) | Out-Null}}
}
