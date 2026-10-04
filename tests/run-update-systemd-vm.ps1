param([ValidateRange(1,3)][int]$Rounds=3)
$ErrorActionPreference='Stop'
$dockerVmProof='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$workspaceVmProof=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
function Invoke-VmProofDocker {param([string[]]$Arguments) & $dockerVmProof @Arguments;if($LASTEXITCODE -ne 0){throw 'Isolated systemd VM verification failed'}}
Invoke-VmProofDocker @('build','-t','search-systemd-vm:20261004',(Join-Path $PSScriptRoot 'fixtures/update-systemd-vm'))
foreach($roundVmProof in 1..$Rounds){
 $containerVmProof="search-systemd-vm-$roundVmProof-20261004";$createdVmProof=$false
 try{
  $existingVmProof=& $dockerVmProof ps -a --filter "name=^/$containerVmProof`$" --format '{{.Names}}';if($LASTEXITCODE -ne 0 -or $existingVmProof){throw 'Dedicated VM name unavailable; preserved'}
  Invoke-VmProofDocker @('run','-d','--name',$containerVmProof,'--network','none','--cap-drop','ALL','--security-opt','no-new-privileges','-e','SEARCH_TEST_MODE=1','search-systemd-vm:20261004','sleep','infinity') | Out-Null;$createdVmProof=$true
  Invoke-VmProofDocker @('exec',$containerVmProof,'mkdir','-p','/seed')
  foreach($partVmProof in @('user-data','meta-data','network-config','bootstrap.sh','run-vm.sh')){Invoke-VmProofDocker @('cp',"$workspaceVmProof/tests/fixtures/update-systemd-vm/$partVmProof","${containerVmProof}:/seed/$partVmProof")}
  foreach($partVmProof in @('bin/update-execution-worker.php','bin/systemd/search-update-execution.service','tests/update-systemd-manager.php')){$leafVmProof=Split-Path $partVmProof -Leaf;Invoke-VmProofDocker @('cp',"$workspaceVmProof/$partVmProof","${containerVmProof}:/seed/$leafVmProof")}
  Invoke-VmProofDocker @('exec',$containerVmProof,'sed','-i','s/\r$//','/seed/bootstrap.sh','/seed/run-vm.sh')
  Invoke-VmProofDocker @('exec',$containerVmProof,'touch','/tmp/search-systemd-vm-test-only')
  Invoke-VmProofDocker @('exec',$containerVmProof,'php','-l','/seed/update-systemd-manager.php')
  foreach($commandRoundVmProof in 1..3){Invoke-VmProofDocker @('exec',$containerVmProof,'php','/seed/update-systemd-manager.php','--test-command')}
  Invoke-VmProofDocker @('exec',$containerVmProof,'bash','/seed/run-vm.sh')
  Write-Output "Systemd VM round $roundVmProof completed."
 }finally{if($createdVmProof){Invoke-VmProofDocker @('rm','-f',$containerVmProof) | Out-Null}}
}
