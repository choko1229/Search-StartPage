param([switch]$KeepReady,[switch]$SkipCandidate)
$ErrorActionPreference='Stop'
$extensionDocker='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$extensionNode='C:\Users\choko\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'
$extensionWorkspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$extensionNetwork='search-extension-ui-20261006';$extensionNetworkCreated=$false;$extensionApps=@();$extensionDatabases=@();$extensionReady=$false
function Invoke-ExtensionHttpDocker {param([string[]]$Arguments) & $extensionDocker @Arguments; if($LASTEXITCODE -ne 0){throw 'Isolated extension HTTP preparation failed'}}
try{
 $extensionExisting=& $extensionDocker network ls --filter "name=^$extensionNetwork`$" --format '{{.Name}}'
 if($LASTEXITCODE -ne 0 -or $extensionExisting){throw 'Dedicated extension network unavailable'}
 # Docker Desktop does not expose published ports on this internal bridge; use a dedicated bridge.
 # Only these disposable apps/DBs join it, and HTTP ports are bound to host loopback.
 Invoke-ExtensionHttpDocker @('network','create',$extensionNetwork) | Out-Null;$extensionNetworkCreated=$true
 foreach($extensionEngine in @('mysql','mariadb')){
  $extensionDatabase="search-extension-ui-$extensionEngine-20261006";$extensionApp="search-extension-ui-app-$extensionEngine-20261006"
  $extensionPort=if($extensionEngine -eq 'mysql'){8115}else{8116};$extensionVhost="extension-$extensionEngine.localhost:$extensionPort"
  foreach($extensionName in @($extensionDatabase,$extensionApp)){
   $extensionExisting=& $extensionDocker ps -a --filter "name=^/$extensionName`$" --format '{{.Names}}'
   if($LASTEXITCODE -ne 0 -or $extensionExisting){throw 'Dedicated extension container name unavailable'}
  }
  $extensionPassword=[Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N')
  $extensionPrefix=if($extensionEngine -eq 'mysql'){'MYSQL'}else{'MARIADB'};$extensionImage=if($extensionEngine -eq 'mysql'){'mysql:8.0'}else{'mariadb:10.11'}
  Invoke-ExtensionHttpDocker @('run','-d','--name',$extensionDatabase,'--network',$extensionNetwork,'--tmpfs','/var/lib/mysql:rw,size=512m','-e',"${extensionPrefix}_DATABASE=extension_ui",'-e',"${extensionPrefix}_USER=extension_test",'-e',"${extensionPrefix}_PASSWORD=$extensionPassword",'-e',"${extensionPrefix}_RANDOM_ROOT_PASSWORD=yes",$extensionImage) | Out-Null
  $extensionDatabases+=$extensionDatabase
  Invoke-ExtensionHttpDocker @('run','-d','--name',$extensionApp,'--network',$extensionNetwork,'--user','www-data','--cap-drop','ALL','--security-opt','no-new-privileges','-p',"127.0.0.1:${extensionPort}:8080",'-e','SEARCH_TEST_MODE=1','-e','SEARCH_LOCAL_DEVELOPMENT=1','-e',"TEST_EXTENSION_HOST=$extensionDatabase",'-e',"TEST_EXTENSION_PASSWORD=$extensionPassword",'-e',"TEST_EXTENSION_VHOST=$extensionVhost",'search-real-release-php-8.3:20261005','sleep','infinity') | Out-Null
  $extensionApps+=$extensionApp
  Invoke-ExtensionHttpDocker @('exec',$extensionApp,'mkdir','-p','/tmp/search-extension-web/config','/tmp/search-extension-web/storage','/tmp/search-extension-web/tests/fixtures')
  foreach($extensionPart in @('app','public','lang','database','bin','extension','VERSION')){Invoke-ExtensionHttpDocker @('cp',"$extensionWorkspace/$extensionPart","${extensionApp}:/tmp/search-extension-web/")}
  foreach($extensionPart in @('config.example.php','providers.php')){Invoke-ExtensionHttpDocker @('cp',"$extensionWorkspace/config/$extensionPart","${extensionApp}:/tmp/search-extension-web/config/$extensionPart")}
  Invoke-ExtensionHttpDocker @('cp',"$extensionWorkspace/tests/fixtures/extension","${extensionApp}:/tmp/search-extension-web/tests/fixtures/")
  Invoke-ExtensionHttpDocker @('exec',$extensionApp,'php','-r',"file_put_contents('/tmp/search-extension-web/storage/extension-test-only','isolated-extension-verification');")
  foreach($extensionPart in @('setup.php','router.php','login.php')){Invoke-ExtensionHttpDocker @('exec',$extensionApp,'php','-l',"/tmp/search-extension-web/tests/fixtures/extension/$extensionPart")}
  Invoke-ExtensionHttpDocker @('exec',$extensionApp,'php','/tmp/search-extension-web/tests/fixtures/extension/setup.php')
  Invoke-ExtensionHttpDocker @('exec','-d',$extensionApp,'php','-d','display_errors=0','-d','memory_limit=512M','-S','0.0.0.0:8080','-t','/tmp/search-extension-web/public','/tmp/search-extension-web/tests/fixtures/extension/router.php')
  Invoke-ExtensionHttpDocker @('exec',$extensionApp,'php','-r','$h=curl_init("http://127.0.0.1:8080/api/user");curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["Host: ".getenv("TEST_EXTENSION_VHOST")],CURLOPT_TIMEOUT=>3]);curl_exec($h);echo json_encode(["internal_http_status"=>curl_getinfo($h,CURLINFO_HTTP_CODE),"network_errno"=>curl_errno($h)]).PHP_EOL;')
  foreach($extensionRound in 1..3){
   & $extensionNode (Join-Path $extensionWorkspace 'tests/extension-http.test.mjs') "http://127.0.0.1:$extensionPort" "http://$extensionVhost" $extensionRound
   if($LASTEXITCODE -ne 0){throw 'Real extension HTTP verification failed'}
   Write-Output "Extension HTTP $extensionEngine round $extensionRound completed."
  }
  if(!$SkipCandidate){
   $extensionOutput=Join-Path $extensionWorkspace ".test-output/extension-$extensionEngine-20261006"
   if(Test-Path -LiteralPath $extensionOutput){throw 'Dedicated extension candidate already exists'}
   Invoke-ExtensionHttpDocker @('exec',$extensionApp,'php','/tmp/search-extension-web/bin/prepare-extension.php','/tmp/extension-candidate',"http://$extensionVhost")
   Invoke-ExtensionHttpDocker @('cp',"${extensionApp}:/tmp/extension-candidate",$extensionOutput)
  }
 }
 $extensionReady=$true
 if($KeepReady){Write-Output 'Dedicated Web/extension UI environments remain ready for actual Chrome verification.'}
}finally{
 if(!$KeepReady -or !$extensionReady){
  foreach($extensionApp in $extensionApps){Invoke-ExtensionHttpDocker @('rm','-f',$extensionApp) | Out-Null}
  foreach($extensionDatabase in $extensionDatabases){
   $extensionTmpfs=& $extensionDocker inspect --format '{{json .HostConfig.Tmpfs}}' $extensionDatabase
   if($LASTEXITCODE -ne 0 -or $extensionTmpfs -ne '{"/var/lib/mysql":"rw,size=512m"}'){throw 'Dedicated DB tmpfs mismatch; preserved'}
   Invoke-ExtensionHttpDocker @('rm','-f',$extensionDatabase) | Out-Null
  }
  if($extensionNetworkCreated){Invoke-ExtensionHttpDocker @('network','rm',$extensionNetwork) | Out-Null}
 }
}
