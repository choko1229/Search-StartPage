<?php
declare(strict_types=1);

ini_set('display_errors','0');
try {
    require_once dirname(__DIR__).'/app/Services/UpdateAccess.php';
    $updateAccess=new App\Services\UpdateAccess(dirname(__DIR__).'/storage/updates/access');
    $updateAccessLease=$updateAccess->enter();
    if($updateAccessLease===null)throw new RuntimeException('UPDATE_IN_PROGRESS');
    require_once dirname(__DIR__).'/app/Services/UpdateWebCache.php';
    App\Services\UpdateWebCache::synchronize(dirname(__DIR__),$updateAccess->generation());
}catch(Throwable){
    http_response_code(503);header('Cache-Control: no-store');header('Retry-After: 30');header('X-Content-Type-Options: nosniff');header('Content-Security-Policy: default-src \'none\'; base-uri \'none\'; frame-ancestors \'none\'');
    $japanese=preg_match('/^ja(?:[-,;]|$)/i',$_SERVER['HTTP_ACCEPT_LANGUAGE']??'')===1;
    $message=$japanese?'更新作業中です。しばらくしてから再読み込みしてください。':'An update is in progress. Please reload shortly.';
    if(str_starts_with($_SERVER['REQUEST_URI']??'','/api/')){header('Content-Type: application/json; charset=utf-8');if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')echo json_encode(['success'=>false,'error'=>['code'=>'UPDATE_IN_PROGRESS','message'=>$message]],JSON_UNESCAPED_UNICODE);}
    else {header('Content-Type: text/html; charset=utf-8');if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')echo '<!doctype html><html lang="'.($japanese?'ja':'en').'"><meta charset="utf-8"><title>'.($japanese?'更新作業中':'Update in progress').'</title><main><h1>'.($japanese?'更新作業中':'Update in progress').'</h1><p>'.$message.'</p></main></html>';}
    exit;
}
// Retained through error/shutdown logging; destructor releases after the request completes.
require dirname(__DIR__) . '/app/bootstrap.php';
