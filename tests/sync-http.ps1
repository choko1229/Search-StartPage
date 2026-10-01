param([ValidatePattern('^search-test-[0-9]{14}$')][string]$Project = 'search-test-20261002020651')
$ErrorActionPreference='Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
$taskDocker=Join-Path $env:LOCALAPPDATA 'Programs/DockerDesktop/resources/bin/docker.exe'
$taskNode=Join-Path $env:USERPROFILE '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node.exe'
$taskFixture=Join-Path $PWD '.test-output/sync-http-credentials.json'
foreach($taskDb in @('mysql','mariadb')) {
    $taskContainer="$Project-app-$taskDb-1"
    & $taskDocker cp tests/sync-http-fixture.php "${taskContainer}:/var/www/app/tests/sync-http-fixture.php"
    if($LASTEXITCODE -ne 0){throw 'Fixture copy failed'}
    try {
        $taskCredentials=& $taskDocker exec -u www-data $taskContainer php tests/sync-http-fixture.php create
        if($LASTEXITCODE -ne 0){throw 'Fixture creation failed'}
        [IO.File]::WriteAllText($taskFixture,($taskCredentials -join "`n"),[Text.UTF8Encoding]::new($false))
        $taskCredentials=$null
        $taskOrigin=if($taskDb -eq 'mysql'){'http://127.0.0.1:8080'}else{'http://127.0.0.1:8081'}
        & $taskNode tests/sync-http.test.mjs $taskFixture $taskOrigin
        if($LASTEXITCODE -ne 0){throw "Live sync failed: $taskDb"}
    } finally {
        & $taskDocker exec -u www-data $taskContainer php tests/sync-http-fixture.php cleanup
        if(Test-Path -LiteralPath $taskFixture){Remove-Item -LiteralPath $taskFixture}
    }
}
