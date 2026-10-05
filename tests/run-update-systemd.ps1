param([ValidateRange(1,3)][int]$Rounds=3)
$ErrorActionPreference='Stop'
$dockerSystemdCheck='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$workspaceSystemdCheck=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$containerSystemdCheck='search-systemd-static-20261004'
$createdSystemdCheck=$false
function Invoke-SystemdCheckDocker {
 param([string[]]$Arguments)
 & $dockerSystemdCheck @Arguments
 if($LASTEXITCODE -ne 0){throw 'Isolated systemd check failed'}
}
try {
 $existingSystemdCheck=& $dockerSystemdCheck ps -a --filter "name=^/$containerSystemdCheck`$" --format '{{.Names}}'
 if($LASTEXITCODE -ne 0 -or $existingSystemdCheck){throw 'Dedicated name unavailable; preserved'}
 Invoke-SystemdCheckDocker @('build','-t','search-systemd-static:20261004',(Join-Path $PSScriptRoot 'fixtures/update-systemd'))
 Invoke-SystemdCheckDocker @('run','-d','--name',$containerSystemdCheck,'--network','none','--cap-drop','ALL','--security-opt','no-new-privileges','-e','SEARCH_TEST_MODE=1','search-systemd-static:20261004','sleep','infinity') | Out-Null
 $createdSystemdCheck=$true
 Invoke-SystemdCheckDocker @('exec',$containerSystemdCheck,'mkdir','-p','/srv/search-startpage/bin/systemd','/srv/search-startpage/tests','/srv/search-startpage/storage')
 foreach($partSystemdCheck in @('bin/update-execution-worker.php','bin/systemd/search-update-execution.service','bin/systemd/stop-update-execution.sh','tests/update-systemd-unit.php','tests/update-execution-worker.php')){
  Invoke-SystemdCheckDocker @('cp',"$workspaceSystemdCheck/$partSystemdCheck","${containerSystemdCheck}:/srv/search-startpage/$partSystemdCheck")
 }
 Invoke-SystemdCheckDocker @('exec',$containerSystemdCheck,'chmod','0644','/srv/search-startpage/bin/systemd/search-update-execution.service')
 Invoke-SystemdCheckDocker @('exec',$containerSystemdCheck,'sed','-i','s/\r$//','/srv/search-startpage/bin/systemd/stop-update-execution.sh')
 Invoke-SystemdCheckDocker @('exec',$containerSystemdCheck,'sh','-n','/srv/search-startpage/bin/systemd/stop-update-execution.sh')
 Invoke-SystemdCheckDocker @('exec',$containerSystemdCheck,'touch','/tmp/update-systemd-test-only')
 Invoke-SystemdCheckDocker @('exec',$containerSystemdCheck,'php','-l','/srv/search-startpage/tests/update-systemd-unit.php')
 foreach($roundSystemdCheck in 1..$Rounds){
  Invoke-SystemdCheckDocker @('exec','-u','www-data',$containerSystemdCheck,'php','/srv/search-startpage/tests/update-systemd-unit.php')
  Invoke-SystemdCheckDocker @('exec','-u','www-data',$containerSystemdCheck,'php','/srv/search-startpage/tests/update-execution-worker.php')
  Write-Output "Static unit and process round $roundSystemdCheck completed."
 }
} finally {
 if($createdSystemdCheck){Invoke-SystemdCheckDocker @('rm','-f',$containerSystemdCheck) | Out-Null}
}
