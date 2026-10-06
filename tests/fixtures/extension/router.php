<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);$vhost=getenv('TEST_EXTENSION_VHOST');
if(getenv('SEARCH_TEST_MODE')!=='1'||$root!=='/tmp/search-extension-web'||!is_file($root.'/storage/extension-test-only')
    ||!in_array($vhost,['extension-mysql.localhost:8115','extension-mariadb.localhost:8116'],true)
    ||($_SERVER['HTTP_HOST']??'')!==$vhost){http_response_code(404);exit;}
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if($path==='/_test/login'){require __DIR__.'/login.php';return;}
$public=realpath($root.'/public');$file=is_string($path)?realpath($public.$path):false;
if($file!==false&&str_starts_with($file,$public.DIRECTORY_SEPARATOR)&&is_file($file)&&pathinfo($file,PATHINFO_EXTENSION)!=='php')return false;
require $root.'/public/index.php';
