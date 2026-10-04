$ErrorActionPreference='Stop'
$dockerMariaUi='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$workspaceMariaUi=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$databaseMariaUi='search-update-backup-mariadb-20261004';$appMariaUi='search-update-mariadb-ui-20261004';$createdMariaDb=$false;$createdMariaApp=$false;$readyMariaUi=$false
function Invoke-MariaUiDocker { param([string[]]$Arguments) & $dockerMariaUi @Arguments; if($LASTEXITCODE -ne 0){throw 'Isolated MariaDB update UI setup failed'} }
try{
 foreach($nameMariaUi in @($databaseMariaUi,$appMariaUi)){$existingMariaUi=& $dockerMariaUi ps -a --filter "name=^/$nameMariaUi`$" --format '{{.Names}}';if($LASTEXITCODE -ne 0 -or $existingMariaUi){throw 'Dedicated name exists; preserved'}}
 $passwordMariaUi=[Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N')
 Invoke-MariaUiDocker @('run','-d','--name',$databaseMariaUi,'--network','search-phase9-roles-20261004_default','--tmpfs','/var/lib/mysql:rw,size=512m','-e','MARIADB_DATABASE=update_backup','-e','MARIADB_USER=backup','-e',"MARIADB_PASSWORD=$passwordMariaUi",'-e','MARIADB_RANDOM_ROOT_PASSWORD=yes','mariadb:10.11') | Out-Null;$createdMariaDb=$true
 Invoke-MariaUiDocker @('run','-d','--name',$appMariaUi,'--network','search-phase9-roles-20261004_default','-p','127.0.0.1:8108:80','-e','SEARCH_TEST_MODE=1','-e','SEARCH_LOCAL_DEVELOPMENT=1','-e',"TEST_BACKUP_HOST=$databaseMariaUi",'-e',"TEST_BACKUP_PASSWORD=$passwordMariaUi",'search-phase9-roles-20261004-app-mysql','sleep','infinity') | Out-Null;$createdMariaApp=$true
 Invoke-MariaUiDocker @('exec',$appMariaUi,'mkdir','-p','/var/www/app/config','/var/www/app/storage')
 foreach($partMariaUi in @('app','public','lang','database','tests','bin')){Invoke-MariaUiDocker @('cp',"$workspaceMariaUi/$partMariaUi","${appMariaUi}:/var/www/app/")}
 foreach($partMariaUi in @('config/config.example.php','config/providers.php','VERSION')){Invoke-MariaUiDocker @('cp',"$workspaceMariaUi/$partMariaUi","${appMariaUi}:/var/www/app/$partMariaUi")}
 Invoke-MariaUiDocker @('exec',$appMariaUi,'touch','/var/www/app/storage/web-cache-test-only')
 foreach($roundMariaUi in 1..3){Invoke-MariaUiDocker @('exec',$appMariaUi,'php','/var/www/app/tests/update-requests-http.php');Invoke-MariaUiDocker @('exec',$appMariaUi,'php','/var/www/app/tests/run.php');Write-Output "MariaDB update UI regression $roundMariaUi completed."}
 Invoke-MariaUiDocker @('exec',$appMariaUi,'php','-l','/var/www/app/tests/update-ui-development.php')
 Invoke-MariaUiDocker @('exec',$appMariaUi,'php','/var/www/app/tests/update-ui-development.php')
 Invoke-MariaUiDocker @('exec',$appMariaUi,'chown','-R','www-data:www-data','/var/www/app/live')
 Invoke-MariaUiDocker @('exec',$appMariaUi,'chown','-R','www-data:www-data','/var/www/app/storage')
 Invoke-MariaUiDocker @('exec','-u','www-data',$appMariaUi,'php','/var/www/app/tests/update-ui-observe.php')
 Invoke-MariaUiDocker @('exec',$appMariaUi,'sed','-i','s|/var/www/app/public|/var/www/app/live/public|g','/etc/apache2/sites-available/000-default.conf')
 Invoke-MariaUiDocker @('exec',$appMariaUi,'apache2ctl','start')
 Invoke-MariaUiDocker @('exec','-d','-u','www-data',$appMariaUi,'php','/var/www/app/live/bin/update-execution-worker.php')
 $readyMariaUi=$true;Write-Output 'Dedicated MariaDB update UI ready: http://update-mariadb.localhost:8108/_test/ui-login.php'
}finally{
 if(!$readyMariaUi){if($createdMariaApp){Invoke-MariaUiDocker @('rm','-f',$appMariaUi) | Out-Null};if($createdMariaDb){$tmpfsMariaUi=& $dockerMariaUi inspect --format '{{json .HostConfig.Tmpfs}}' $databaseMariaUi;if($LASTEXITCODE -ne 0 -or $tmpfsMariaUi -ne '{"/var/lib/mysql":"rw,size=512m"}'){throw 'Dedicated tmpfs mismatch; preserved'};Invoke-MariaUiDocker @('rm','-f',$databaseMariaUi) | Out-Null}}
}
