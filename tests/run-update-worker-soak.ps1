param([ValidateRange(60,3600)][int]$Seconds=900,[ValidateRange(1,3)][int]$Rounds=3,[ValidateSet('8.2','8.3')][string[]]$Php=@('8.2','8.3'))
$ErrorActionPreference='Stop'
$soakDocker='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$soakWorkspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
function Invoke-SoakDocker {param([string[]]$Arguments) & $soakDocker @Arguments;if($LASTEXITCODE -ne 0){throw 'Isolated worker soak failed'}}
foreach($soakPhp in $Php){
 $soakContainer="search-worker-soak-$soakPhp-20261005";$soakCreated=$false
 try{
  $soakExisting=& $soakDocker ps -a --filter "name=^/$soakContainer`$" --format '{{.Names}}';if($LASTEXITCODE -ne 0 -or $soakExisting){throw 'Dedicated soak name unavailable; preserved'}
  Invoke-SoakDocker @('run','-d','--name',$soakContainer,'--network','none','--cap-drop','ALL','--security-opt','no-new-privileges','-e','SEARCH_TEST_MODE=1',"php:$soakPhp-fpm-bookworm",'sleep','infinity') | Out-Null;$soakCreated=$true
  Invoke-SoakDocker @('exec',$soakContainer,'mkdir','-p','/tmp/worker-soak-source/bin','/tmp/worker-soak-source/tests')
  foreach($soakPart in @('bin/update-execution-worker.php','tests/update-worker-soak.php')){Invoke-SoakDocker @('cp',"$soakWorkspace/$soakPart","${soakContainer}:/tmp/worker-soak-source/$soakPart")}
  Invoke-SoakDocker @('exec',$soakContainer,'touch','/tmp/update-worker-soak-only')
  Invoke-SoakDocker @('exec',$soakContainer,'php','-l','/tmp/worker-soak-source/tests/update-worker-soak.php')
  foreach($soakRound in 1..$Rounds){Invoke-SoakDocker @('exec','--user','www-data',$soakContainer,'php','/tmp/worker-soak-source/tests/update-worker-soak.php',"$Seconds");Write-Output "Worker soak PHP $soakPhp round $soakRound complete."}
 }finally{if($soakCreated){Invoke-SoakDocker @('rm','-f',$soakContainer) | Out-Null}}
}
