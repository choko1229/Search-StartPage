param([ValidateRange(1,3)][int]$Rounds=3)
$ErrorActionPreference='Stop'
$releaseDocker='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$releaseWorkspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
function Invoke-ReleaseDocker { param([string[]]$Arguments) & $releaseDocker @Arguments; if($LASTEXITCODE -ne 0){throw 'Release preparation check failed'} }
foreach($releasePhp in @('8.2','8.3')){
 $releaseContainer="search-release-preparation-$releasePhp-20261004";$releaseCreated=$false
 try{
  $releaseExisting=& $releaseDocker ps -a --filter "name=^/$releaseContainer`$" --format '{{.Names}}';if($LASTEXITCODE -ne 0 -or $releaseExisting){throw 'Dedicated name unavailable'}
  Invoke-ReleaseDocker @('run','-d','--name',$releaseContainer,'--network','none','--cap-drop','ALL','--security-opt','no-new-privileges','-e','SEARCH_TEST_MODE=1',"php:$releasePhp-fpm-bookworm",'sleep','infinity') | Out-Null;$releaseCreated=$true
  Invoke-ReleaseDocker @('exec',$releaseContainer,'mkdir','-p','/tmp/release-source/config','/tmp/release-source/tests')
  foreach($releasePart in @('app','bin','database','lang','public','README.md','composer.json','VERSION')){Invoke-ReleaseDocker @('cp',"$releaseWorkspace/$releasePart","${releaseContainer}:/tmp/release-source/")}
  foreach($releasePart in @('config/config.example.php','config/providers.php','tests/release-preparation.php','tests/run.php')){Invoke-ReleaseDocker @('cp',"$releaseWorkspace/$releasePart","${releaseContainer}:/tmp/release-source/$releasePart")}
  foreach($releasePart in @('bin/prepare-release.php','tests/release-preparation.php')){Invoke-ReleaseDocker @('exec',$releaseContainer,'php','-l',"/tmp/release-source/$releasePart")}
  foreach($releaseRound in 1..$Rounds){Invoke-ReleaseDocker @('exec',$releaseContainer,'php','/tmp/release-source/tests/release-preparation.php');Invoke-ReleaseDocker @('exec',$releaseContainer,'php','/tmp/release-source/tests/run.php');Write-Output "Release PHP $releasePhp round $releaseRound complete."}
 }finally{if($releaseCreated){Invoke-ReleaseDocker @('rm','-f',$releaseContainer) | Out-Null}}
}
