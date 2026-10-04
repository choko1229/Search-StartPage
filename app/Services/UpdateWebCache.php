<?php
declare(strict_types=1);
namespace App\Services;

/** Runs under the HTTP access lease, before autoload/config/DB. No application dependencies. */
final class UpdateWebCache
{
    public const PROTOCOL=1;
    public static function synchronize(string $root,string $generation): void
    {
        if($generation==='')return;
        if(!preg_match('/^[a-f0-9]{32}$/D',$generation))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
        if(!extension_loaded('Zend OPcache')||!filter_var(ini_get('opcache.enable'),FILTER_VALIDATE_BOOLEAN)
            ||PHP_SAPI==='cli'&&!filter_var(ini_get('opcache.enable_cli'),FILTER_VALIDATE_BOOLEAN))return;
        foreach(['opcache_get_status','opcache_is_script_cached','opcache_invalidate','opcache_reset','opcache_compile_file'] as $function)
            if(!function_exists($function))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
        $directory=$root.'/storage/updates/access';$cursor=$directory;
        while(true){if(is_link($cursor))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');$parent=dirname($cursor);if($parent===$cursor)break;$cursor=$parent;}
        if(!is_dir($directory))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
        $marker=$directory.'/web-cache-'.$generation.'.php';$body='<?php /* update cache '.$generation.' */';
        $path=$directory.'/web-cache.lock';if(is_link($path)||file_exists($path)&&!is_file($path))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
        $lock=@fopen($path,'c');if($lock===false)throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
        try{
            if(!@chmod($path,0600)||!flock($lock,LOCK_EX))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
            self::marker($marker,$body);if(opcache_is_script_cached($marker))return;
            $status=@opcache_get_status(true);
            if(!is_array($status)||!isset($status['scripts'])||!is_array($status['scripts']))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
            foreach($status['scripts'] as $script){
                $file=$script['full_path']??null;if(!is_string($file))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
                $prefix=rtrim(str_replace('\\','/',$root),'/').'/';$normalized=str_replace('\\','/',$file);
                if(PHP_OS_FAMILY==='Windows'){$prefix=strtolower($prefix);$normalized=strtolower($normalized);}
                $relative=str_starts_with($normalized,$prefix)?substr($normalized,strlen($prefix)):'';
                if(!preg_match('~^(?:app|public|config|lang|database|bin)/~D',$relative))continue;
                if(!@opcache_invalidate($file,true)){
                    // A reset completes after this request. Never load application code meanwhile.
                    @opcache_reset();throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
                }
            }
            clearstatcache(true);
            // The marker belongs to this OPcache instance, unlike a shared on-disk acknowledgement.
            // A full cache or file-update protection can leave it uncached; retry next request safely.
            @opcache_compile_file($marker);
            foreach(glob($directory.'/web-cache-*.php')?:[] as $old){
                if($old===$marker||!preg_match('/^web-cache-[a-f0-9]{32}\.php$/D',basename($old)))continue;
                if(is_link($old)||!is_file($old)||!@unlink($old))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
            }
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private static function marker(string $path,string $body): void
    {
        clearstatcache(true,$path);
        if(is_link($path)||file_exists($path)&&!is_file($path))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
        if(!file_exists($path)){
            $stream=@fopen($path,'x');
            if($stream!==false)try{if(!@chmod($path,0600)||fwrite($stream,$body)!==strlen($body)||!fflush($stream))throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');}finally{fclose($stream);}
        }
        clearstatcache(true,$path);
        if(!is_file($path)||(fileperms($path)&0077)!==0||filesize($path)!==strlen($body)||@file_get_contents($path)!==$body)throw new \RuntimeException('UPDATE_WEB_CACHE_UNAVAILABLE');
    }
}
