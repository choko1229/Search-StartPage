<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/autoload.php';
try {
    $root=dirname(__DIR__);$config=App\Config::load($root);
    if(count($argv)>2||(isset($argv[1])&&$argv[1]!=='--if-due'))throw new App\Http\HttpException(422,'INVALID_INPUT');
    $current=trim(file_get_contents($root.'/VERSION'));
    $state=(new App\Services\UpdateChecks($root.'/storage/updates/checks',$config,$current))->check(onlyIfDue:isset($argv[1]));
    if($state['error']!==null)throw new App\Http\HttpException(502,$state['error']);
    echo json_encode($state,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){fwrite(STDERR,($error instanceof App\Http\HttpException?$error->errorCode:'UPDATE_CHECK_FAILED')."\n");exit(1);}
