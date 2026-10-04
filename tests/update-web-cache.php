<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/UpdateWebCache.php';
use App\Services\UpdateWebCache;
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
if(!function_exists('opcache_get_status')||!ini_get('opcache.enable_cli'))throw new RuntimeException('Test requires CLI OPcache');
$root=sys_get_temp_dir().'/update-web-cache-'.bin2hex(random_bytes(8));mkdir($root,0700);mkdir($root.'/app');mkdir($root.'/storage');mkdir($root.'/storage/updates');mkdir($root.'/storage/updates/access',0700);$count=0;
$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$remove=function($path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
$reject=static function(callable $operation,string $name)use($check){try{$operation();throw new LogicException('Unexpected success');}catch(RuntimeException $error){$check($error->getMessage()==='UPDATE_WEB_CACHE_UNAVAILABLE',$name);}};
try{
 $path=$root.'/app/value.php';file_put_contents($path,'<?php return "before";');$check((require $path)==='before','original PHP cached');$check(opcache_is_script_cached($path),'stale-code precondition cached');
 file_put_contents($path,'<?php return "after!";');$check((require $path)==='before','timestamps disabled really serves stale PHP');
 $generation=str_repeat('a',32);UpdateWebCache::synchronize($root,$generation);$check((require $path)==='after!','generation invalidates stale application PHP');
 $marker=$root.'/storage/updates/access/web-cache-'.$generation.'.php';$check(opcache_is_script_cached($marker)&&(fileperms($marker)&0077)===0,'private cache acknowledgement in current OPcache instance');
 file_put_contents($path,'<?php return "later!";');UpdateWebCache::synchronize($root,$generation);$check((require $path)==='after!','same generation reuses warmed cache');
 $next=str_repeat('b',32);UpdateWebCache::synchronize($root,$next);$check((require $path)==='later!'&&!file_exists($marker),'next generation invalidates and cleans old marker');
 $reject(fn()=>UpdateWebCache::synchronize($root,'invalid'),'invalid generation refused');
 $marker=$root.'/storage/updates/access/web-cache-'.$next.'.php';chmod($marker,0644);$reject(fn()=>UpdateWebCache::synchronize($root,$next),'public acknowledgement permission refused');chmod($marker,0600);file_put_contents($marker,'corrupt');$reject(fn()=>UpdateWebCache::synchronize($root,$next),'corrupt acknowledgement fails closed before app load');unlink($marker);
 file_put_contents($root.'/outside','preserve');symlink($root.'/outside',$marker);$reject(fn()=>UpdateWebCache::synchronize($root,$next),'symlink acknowledgement refused');$check(file_get_contents($root.'/outside')==='preserve','symlink target unchanged');unlink($marker);
 unlink($root.'/storage/updates/access/web-cache.lock');symlink($root.'/outside',$root.'/storage/updates/access/web-cache.lock');$reject(fn()=>UpdateWebCache::synchronize($root,$next),'symlink cache lock refused');
 echo "$count Web cache checks passed.\n";
}finally{$remove($root);}
