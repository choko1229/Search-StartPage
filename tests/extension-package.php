<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
set_error_handler(static function(int $severity,string $message):never{throw new ErrorException($message,0,$severity);});
$root=dirname(__DIR__);$temporary=sys_get_temp_dir().'/extension-package-'.bin2hex(random_bytes(8));mkdir($temporary,0700);
$copy=static function(string $from,string $to)use(&$copy):void{
    if(is_link($from))throw new RuntimeException('Unexpected source link');
    if(is_dir($from)){mkdir($to,0700);foreach(scandir($from) as $name)if($name!=='.'&&$name!=='..')$copy($from.'/'.$name,$to.'/'.$name);}
    else copy($from,$to);
};
$remove=static function(string $path)use(&$remove):void{
    if(is_link($path)||is_file($path)){unlink($path);return;}
    foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);
};
$count=0;$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$reject=static function(callable $action,string $name)use($check):void{try{$action();}catch(Throwable){$check(true,$name);return;}$check(false,$name);};
try{
    $source=$temporary.'/source';mkdir($source,0700);
    foreach(['app','bin','lang','public','extension','VERSION'] as $part)$copy($root.'/'.$part,$source.'/'.$part);
    mkdir($source.'/config',0700);$copy($root.'/config/providers.php',$source.'/config/providers.php');
    file_put_contents($source.'/config/config.php','generated_extension_secret');mkdir($source.'/storage');file_put_contents($source.'/storage/private.txt','generated_extension_user_data');
    $builder=new App\Services\ExtensionPackageBuilder();$output=$temporary.'/package';$result=$builder->build($source,$output);
    $manifest=json_decode(file_get_contents($output.'/manifest.json'),true,flags:JSON_THROW_ON_ERROR);
    $check($manifest['manifest_version']===3&&$manifest['chrome_url_overrides']===['newtab'=>'newtab.html'],'Manifest V3 replaces only New Tab');
    $check(!isset($manifest['permissions'],$manifest['host_permissions'],$manifest['content_scripts'],$manifest['externally_connectable']),'packaging does not grant additional access');
    $check($manifest['content_security_policy']['extension_pages']==="script-src 'self'; object-src 'none'; base-uri 'none'; frame-src 'none';",'packaged executable scripts only, no embedded remote frame');
    foreach(['ja'=>'newtab.html','en'=>'newtab-en.html'] as $locale=>$page){
        $html=file_get_contents($output.'/'.$page);
        $check(str_contains($html,'<html lang="'.$locale.'">')&&str_contains($html,'id="query"')&&str_contains($html,'id="favorites-grid"')&&str_contains($html,'src="/assets/js/search.js"'),'shared search/favorites entry in '.$locale);
        preg_match('~<script type="application/json" id="search-bootstrap">(.*?)</script>~s',$html,$boot);
        $data=json_decode($boot[1],true,flags:JSON_THROW_ON_ERROR);
        $check($data['messages']===(new App\Helpers\Translator($source,$locale))->messages(),'complete shared translations in '.$locale);
        $check($data['providers']===App\Services\ProviderPresets::client(App\Services\ProviderPresets::validate(require $source.'/config/providers.php')),'shared provider presets in '.$locale);
        $check(!str_contains($html,'<?php')&&!preg_match('/\son[a-z]+\s*=/i',$html)&&!str_contains($html,'_csrf'),'static page excludes PHP, executable inline handlers and server session tokens in '.$locale);
    }
    foreach($result['files'] as $path=>$hash){
        $check(hash_equals($hash,hash_file('sha256',$output.'/'.$path)),'generated file digest '.$path);
        $body=file_get_contents($output.'/'.$path);
        $check(!str_contains($body,'generated_extension_secret')&&!str_contains($body,'generated_extension_user_data'),'private input excluded '.$path);
    }
    $check(!is_dir($output.'/config')&&!is_dir($output.'/storage')&&!is_dir($output.'/app'),'only static assets and rendered UI packaged');
    $process=proc_open([PHP_BINARY,$source.'/bin/prepare-extension.php',$temporary.'/cli-package'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$source);
    if(!is_resource($process))throw new RuntimeException('CLI process unavailable');
    fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $check(proc_close($process)===0&&$stderr==='','actual CLI generates a warning-free static extension');
    $metadata=json_decode($stdout,true,flags:JSON_THROW_ON_ERROR);
    $check($metadata===['version'=>$result['version'],'files'=>count($result['files'])],'actual CLI reports the generated version and file count');
    $check(hash_file('sha256',$temporary.'/cli-package/manifest.json')===hash_file('sha256',$output.'/manifest.json'),'CLI and direct builder produce the same manifest');
    $check(hash_file('sha256',$source.'/public/assets/js/search.js')===hash_file('sha256',$output.'/assets/js/search.js')&&hash_file('sha256',$source.'/public/assets/css/glass.css')===hash_file('sha256',$output.'/assets/css/glass.css'),'Web and extension share unchanged UI code');
    $original=hash_file('sha256',$output.'/manifest.json');
    $reject(fn()=>$builder->build($source,$output),'existing package refused');
    $check(hash_file('sha256',$output.'/manifest.json')===$original,'existing package remains intact');
    $reject(fn()=>$builder->build($source,$source.'/nested'),'source directory output refused');
    symlink($temporary,$temporary.'/parent-link');$reject(fn()=>$builder->build($source,$temporary.'/parent-link/package-link'),'symlink parent refused');unlink($temporary.'/parent-link');
    file_put_contents($source.'/public/assets/js/unexpected.php','<?php');
    $reject(fn()=>$builder->build($source,$temporary.'/invalid'),'non-static asset refused');
    $check(!file_exists($temporary.'/invalid'),'failed build removes only its newly owned output');
    $check(file_get_contents($source.'/config/config.php')==='generated_extension_secret','private source remains unchanged');
    echo "$count extension packaging checks passed.\n";
}finally{$remove($temporary);}
