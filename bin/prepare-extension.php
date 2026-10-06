<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/autoload.php';
try{
    if(count($argv)!==2)throw new RuntimeException('INVALID_EXTENSION_ARGUMENTS');
    $result=(new App\Services\ExtensionPackageBuilder())->build(dirname(__DIR__),$argv[1]);
    echo json_encode(['version'=>$result['version'],'files'=>count($result['files'])],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){
    $code=$error->getMessage();
    fwrite(STDERR,preg_match('/^[A-Z_]+$/D',$code)?$code."\n":"EXTENSION_BUILD_FAILED\n");exit(1);
}
