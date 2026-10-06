param([ValidateRange(1,3)][int]$Rounds=3)
$ErrorActionPreference='Stop'
$extensionDocker='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$extensionWorkspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
function Invoke-ExtensionDocker {param([string[]]$Arguments) & $extensionDocker @Arguments; if($LASTEXITCODE -ne 0){throw 'Extension package verification failed'}}
foreach($extensionPhp in @('8.2','8.3')){
 $extensionName="search-extension-package-$extensionPhp-20261006";$extensionCreated=$false
 try{
  $extensionExisting=& $extensionDocker ps -a --filter "name=^/$extensionName`$" --format '{{.Names}}'
  if($LASTEXITCODE -ne 0 -or $extensionExisting){throw 'Dedicated extension verification name unavailable'}
  Invoke-ExtensionDocker @('run','-d','--name',$extensionName,'--network','none','--user','www-data','--cap-drop','ALL','--security-opt','no-new-privileges','-e','SEARCH_TEST_MODE=1',"php:$extensionPhp-cli-bookworm",'sleep','infinity') | Out-Null;$extensionCreated=$true
  Invoke-ExtensionDocker @('exec',$extensionName,'mkdir','-p','/tmp/extension-source/config','/tmp/extension-source/tests')
  foreach($extensionPart in @('app','lang','public','extension','bin','VERSION')){Invoke-ExtensionDocker @('cp',"$extensionWorkspace/$extensionPart","${extensionName}:/tmp/extension-source/")}
  foreach($extensionPart in @('config/providers.php','tests/extension-package.php','tests/run.php')){Invoke-ExtensionDocker @('cp',"$extensionWorkspace/$extensionPart","${extensionName}:/tmp/extension-source/$extensionPart")}
  foreach($extensionPart in @('app/Services/ExtensionPackageBuilder.php','extension/layout.php','bin/prepare-extension.php','tests/extension-package.php')){Invoke-ExtensionDocker @('exec',$extensionName,'php','-l',"/tmp/extension-source/$extensionPart")}
  foreach($extensionRound in 1..$Rounds){Invoke-ExtensionDocker @('exec',$extensionName,'php','/tmp/extension-source/tests/extension-package.php');Invoke-ExtensionDocker @('exec',$extensionName,'php','/tmp/extension-source/tests/run.php');Write-Output "Extension PHP $extensionPhp round $extensionRound complete."}
 }finally{if($extensionCreated){Invoke-ExtensionDocker @('rm','-f',$extensionName) | Out-Null}}
}
