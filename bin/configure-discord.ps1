param(
    [string]$Container = 'search-phase1-ui',
    [string]$SiteUrl = 'http://127.0.0.1:8082'
)
$ErrorActionPreference = 'Stop'
$dockerCommand = Get-Command docker -ErrorAction SilentlyContinue
$dockerExe = if ($dockerCommand) { $dockerCommand.Source } else { "$env:LOCALAPPDATA\Programs\DockerDesktop\resources\bin\docker.exe" }
if (-not (Test-Path -LiteralPath $dockerExe)) { throw 'Docker CLI was not found.' }
Write-Host "Register this exact Discord OAuth redirect URL: $($SiteUrl.TrimEnd('/'))/auth/discord/callback"
$clientId = Read-Host 'Discord application Client ID'
if ($clientId -notmatch '^\d{17,20}$') { throw 'Invalid Client ID.' }
$secret = Read-Host 'Discord Client Secret (hidden)' -AsSecureString
$secretPointer = [IntPtr]::Zero
$php = @'
$path='/var/www/app/config/config.php';
$data=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);
if (!preg_match('/^[0-9]{17,20}$/D',$data['client_id']) || !is_string($data['client_secret']) || $data['client_secret']==='') { exit(2); }
$url=rtrim($data['site_url'],'/');
$parts=parse_url($url);
if (!filter_var($url,FILTER_VALIDATE_URL) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['scheme'],['http','https'],true)) { exit(2); }
if ($parts['scheme']==='http' && !in_array($parts['host'],['127.0.0.1','localhost'],true)) { exit(2); }
$config=require $path;
$config['discord']=['client_id'=>$data['client_id'],'client_secret'=>$data['client_secret']];
$config['site']['url']=$url;
$config['session']['secure']=$parts['scheme']==='https';
$temp=tempnam(dirname($path),'.config-');
chmod($temp,0600);
try {
    $text="<?php\ndeclare(strict_types=1);\nreturn ".var_export($config,true).";\n";
    if (file_put_contents($temp,$text,LOCK_EX)!==strlen($text) || !rename($temp,$path)) { exit(3); }
} finally { if (is_file($temp)) { unlink($temp); } }
echo "Discord configuration saved.\n";
'@
try {
    $secretPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
    $plainSecret = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secretPointer)
    $payload = @{client_id=$clientId; client_secret=$plainSecret; site_url=$SiteUrl} | ConvertTo-Json -Compress
    $payload | & $dockerExe exec --user www-data -i $Container php -r $php
    if ($LASTEXITCODE -ne 0) { throw 'Configuration failed. No credentials were printed.' }
} finally {
    if ($secretPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secretPointer) }
    $plainSecret = $null
    $payload = $null
    $secret.Dispose()
}
Write-Host "Open $SiteUrl/account to test Discord sign-in."
