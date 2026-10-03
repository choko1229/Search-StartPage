<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/autoload.php';
if(count($argv)>2||isset($argv[1])&&!in_array($argv[1],['--apply','--dry-run'],true)){
    fwrite(STDERR,"Usage: php bin/cleanup-backgrounds.php [--dry-run|--apply]\n");exit(2);
}
try {
    $root=dirname(__DIR__);$config=App\Config::load($root);
    if(!$config->get('installed'))throw new RuntimeException('NOT_INSTALLED');
    $pdo=App\Database\Database::connect($config->get('database'));
    $result=(new App\Services\BackgroundCleanup($root,new App\Repositories\BackgroundRepository($pdo)))->run(($argv[1]??'')==='--apply');
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
    exit($result['failed']?1:0);
}catch(Throwable){fwrite(STDERR,"Background cleanup unavailable. No diagnostics or configuration values are exposed.\n");exit(1);}
