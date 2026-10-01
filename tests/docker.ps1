param([switch]$StartOnly)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
$dockerCommand = Get-Command docker -ErrorAction SilentlyContinue
$dockerExecutable = if ($dockerCommand) { $dockerCommand.Source } else { $null }
if (-not $dockerExecutable) {
    foreach ($candidate in @("$env:LOCALAPPDATA\Programs\DockerDesktop\resources\bin\docker.exe", 'C:\Program Files\Docker\Docker\resources\bin\docker.exe')) {
        if (Test-Path -LiteralPath $candidate) { $dockerExecutable = $candidate; break }
    }
}
if (-not $dockerExecutable) { throw 'Docker is not available. Start Docker Desktop and open a new terminal.' }
if (-not $env:TEST_DB_PASSWORD) {
    $env:TEST_DB_PASSWORD = [guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N')
}
# Each run gets independent volumes; no existing data is deleted.
$env:COMPOSE_PROJECT_NAME = 'search-test-' + (Get-Date -Format 'yyyyMMddHHmmss')
Write-Host "Disposable project: $env:COMPOSE_PROJECT_NAME"
& $dockerExecutable compose up --build --detach --wait
if ($LASTEXITCODE -ne 0) { throw 'Compose startup failed.' }
if ($StartOnly) {
    Write-Host 'Environment ready at http://localhost:8080 and http://localhost:8081.'
    Write-Host 'Generate the setup key with: docker compose exec --user www-data app-mysql php bin/setup-key.php'
    return
}
foreach ($service in @('app-mysql', 'app-mariadb')) {
    foreach ($test in @('tests/lint.php', 'tests/run.php', 'tests/integration.php', 'tests/search-api.php', 'tests/metadata.php', 'tests/oauth-validation.php', 'tests/auth.php', 'tests/auth-http.php', 'tests/sync.php', 'tests/login-rate-limit.php', 'tests/login-rate-concurrency.php', 'tests/login-rate-http.php')) {
        & $dockerExecutable compose exec --user www-data -T $service php $test
        if ($LASTEXITCODE -ne 0) { throw "Failed: $service $test" }
    }
}
Write-Host 'Automated tests passed. Containers remain available for browser verification.'
Write-Host 'To stop this test project without deleting data: docker compose stop'
