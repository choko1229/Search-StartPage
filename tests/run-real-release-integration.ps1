param([ValidateRange(1,3)][int]$Rounds=3,[ValidateSet('8.2','8.3')][string[]]$PhpVersions=@('8.2','8.3'),[switch]$PreflightOnly)
$ErrorActionPreference='Stop'
$realDocker='C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$realWorkspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$realCandidate=Join-Path $realWorkspace '.test-output/public-release-88554ee/php-8.3-round-3'
$realBaseline=Join-Path $realWorkspace '.test-output/public-release-5981e20/php-8.3-round-3/search-startpage.tar'
$realCandidateHash='a5f630228ae70c82fce5ed1738b8848a2f0d0e688fc4981e3437f9c590479120'
if(!(Test-Path -LiteralPath $realBaseline) -or (Get-FileHash -LiteralPath $realBaseline -Algorithm SHA256).Hash.ToLowerInvariant() -ne '053dcbee9ac93dabb25a83e7fa5195a024923b93968e530abb6edc1a45d0e03e'){throw 'Fixed baseline is missing or changed'}
if((Get-FileHash -LiteralPath (Join-Path $realCandidate 'search-startpage.tar') -Algorithm SHA256).Hash.ToLowerInvariant() -ne $realCandidateHash){throw 'Fixed candidate changed'}
# Resolve actual public metadata before creating any Docker resources. No token/config is read.
try{
 $realRelease=Invoke-RestMethod -Uri 'https://api.github.com/repos/choko1229/Search-StartPage/releases/tags/v0.1.2-dev'
 $realCommit=Invoke-RestMethod -Uri 'https://api.github.com/repos/choko1229/Search-StartPage/commits/v0.1.2-dev'
}catch{throw 'Published fixed release is unavailable; no test environment was created'}
if($realRelease.tag_name -ne 'v0.1.2-dev' -or $realRelease.draft -ne $false -or $realRelease.prerelease -ne $true -or $realRelease.id -lt 1 -or $realCommit.sha -ne '88554eea76dbf5599b86dc14f41fcec5d544900c'){throw 'Published tag or prerelease does not match the authorized source'}
$realNames=@('search-startpage.tar','search-startpage.tar.sha256','release.json')
if($realRelease.assets.Count -ne 3){throw 'Expected exactly three published assets'}
foreach($realName in $realNames){
 $realAsset=@($realRelease.assets | Where-Object name -EQ $realName)
 $realLocal=Join-Path $realCandidate $realName
 if($realAsset.Count -ne 1 -or $realAsset[0].state -ne 'uploaded' -or $realAsset[0].size -ne (Get-Item -LiteralPath $realLocal).Length -or $realAsset[0].digest -ne ('sha256:'+(Get-FileHash -LiteralPath $realLocal -Algorithm SHA256).Hash.ToLowerInvariant())){throw 'Published asset metadata differs from the fixed local artifact'}
}
Write-Output 'Pinned published tag and all three asset sizes/digests passed preflight.'
if($PreflightOnly){return}
function Invoke-RealReleaseDocker {param([string[]]$Arguments) & $realDocker @Arguments; if($LASTEXITCODE -ne 0){throw 'Isolated real release verification failed'}}
$realNetwork='search-real-release-20261005';$realNetworkCreated=$false;$realApps=@();$realDatabases=@()
try{
 $realExisting=& $realDocker network ls --filter "name=^$realNetwork`$" --format '{{.Name}}'
 if($LASTEXITCODE -ne 0 -or $realExisting){throw 'Dedicated network already exists or cannot be inspected'}
 # Internet access is required for the actual GitHub API/CDN; no other app joins this network.
 Invoke-RealReleaseDocker @('network','create',$realNetwork) | Out-Null;$realNetworkCreated=$true
 foreach($realEngine in @('mysql','mariadb')){
  $realDatabase="search-real-release-$realEngine-20261005"
  $realExisting=& $realDocker ps -a --filter "name=^/$realDatabase`$" --format '{{.Names}}'
  if($LASTEXITCODE -ne 0 -or $realExisting){throw 'Dedicated DB already exists or cannot be inspected'}
  $realPassword=[Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N')
  $realPrefix=if($realEngine -eq 'mysql'){'MYSQL'}else{'MARIADB'};$realImage=if($realEngine -eq 'mysql'){'mysql:8.0'}else{'mariadb:10.11'}
  Invoke-RealReleaseDocker @('run','-d','--name',$realDatabase,'--network',$realNetwork,'--tmpfs','/var/lib/mysql:rw,size=512m','-e',"${realPrefix}_DATABASE=release_integration",'-e',"${realPrefix}_USER=release_test",'-e',"${realPrefix}_PASSWORD=$realPassword",'-e',"${realPrefix}_RANDOM_ROOT_PASSWORD=yes",$realImage) | Out-Null
  $realDatabases+=$realDatabase
  foreach($realPhp in $PhpVersions){
   $realPhpImage="search-real-release-php-$realPhp`:20261005"
   Invoke-RealReleaseDocker @('build','--build-arg',"PHP_VERSION=$realPhp",'-t',$realPhpImage,(Join-Path $realWorkspace 'tests/fixtures/real-release'))
   foreach($realRound in 1..$Rounds){
    $realApp="search-real-release-app-$realEngine-$realPhp-$realRound-20261005";$realRoot='/tmp/search-real-release-source'
    $realExisting=& $realDocker ps -a --filter "name=^/$realApp`$" --format '{{.Names}}'
    if($LASTEXITCODE -ne 0 -or $realExisting){throw 'Dedicated app already exists or cannot be inspected'}
    Invoke-RealReleaseDocker @('run','-d','--name',$realApp,'--network',$realNetwork,'--user','www-data','--cap-drop','ALL','--security-opt','no-new-privileges','-e','SEARCH_TEST_MODE=1','-e',"TEST_RELEASE_HOST=$realDatabase",'-e',"TEST_RELEASE_PASSWORD=$realPassword",'-e',"TEST_RELEASE_ID=$($realRelease.id)",$realPhpImage,'sleep','infinity') | Out-Null
    $realApps+=$realApp
    Invoke-RealReleaseDocker @('exec',$realApp,'mkdir','-p',"$realRoot/config","$realRoot/tests")
    Invoke-RealReleaseDocker @('cp',"$realWorkspace/app","${realApp}:$realRoot/")
    Invoke-RealReleaseDocker @('cp',"$realWorkspace/tests/real-release-integration.php","${realApp}:$realRoot/tests/real-release-integration.php")
    Invoke-RealReleaseDocker @('cp',$realBaseline,"${realApp}:$realRoot/baseline.tar")
    Invoke-RealReleaseDocker @('cp',(Join-Path $realCandidate 'search-startpage.tar'),"${realApp}:$realRoot/expected-candidate.tar")
    Invoke-RealReleaseDocker @('exec',$realApp,'php','-r',"file_put_contents('/tmp/search-real-release-source/real-github-release-test-only','isolated-release-verification');")
    Invoke-RealReleaseDocker @('exec',$realApp,'php','-l',"$realRoot/tests/real-release-integration.php")
    Invoke-RealReleaseDocker @('exec',$realApp,'php','-d','display_errors=stderr','-d','log_errors=0',"$realRoot/tests/real-release-integration.php")
    Write-Output "Real GitHub release $realEngine PHP $realPhp round $realRound completed."
   }
  }
 }
}finally{
 foreach($realApp in $realApps){Invoke-RealReleaseDocker @('rm','-f',$realApp) | Out-Null}
 foreach($realDatabase in $realDatabases){
  $realTmpfs=& $realDocker inspect --format '{{json .HostConfig.Tmpfs}}' $realDatabase
  if($LASTEXITCODE -ne 0 -or $realTmpfs -ne '{"/var/lib/mysql":"rw,size=512m"}'){throw 'Dedicated DB tmpfs mismatch; preserved'}
  Invoke-RealReleaseDocker @('rm','-f',$realDatabase) | Out-Null
 }
 if($realNetworkCreated){Invoke-RealReleaseDocker @('network','rm',$realNetwork) | Out-Null}
}
