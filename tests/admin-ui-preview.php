<?php
declare(strict_types=1);
// Manually copy to public/_test in a disposable localhost container only.
if(getenv('SEARCH_TEST_MODE')!=='1'||getenv('SEARCH_LOCAL_DEVELOPMENT')!=='1'){http_response_code(404);exit;}
$path=dirname(__DIR__,2).'/storage/admin-ui-fixture.json';
if(!is_file($path)){http_response_code(404);exit;}
$fixture=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
header("Content-Security-Policy: default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(time()>$fixture['expires']){http_response_code(410);exit('Fixture expired');}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $key=$_POST['key']??null;
    if(!is_string($key)||!hash_equals($fixture['key'],$key)){http_response_code(403);exit('Invalid fixture key');}
    setcookie('search_remember',$fixture['cookie'],['expires'=>time()+900,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    header('Location: /admin',true,303);exit;
}
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin UI verification</title>
<h1>Development administrator sign-in</h1><p>Disposable test account. Normal server authentication and authorization apply.</p>
<form method="post"><label>Fixture key <input type="password" name="key" autocomplete="off" required></label><button type="submit">Open administration</button></form></html>
