<?php
declare(strict_types=1);
namespace App\Services;

use App\Helpers\{Translator,View};

/** Generates static New Tab pages from the same views and assets used by the Web application. */
final class ExtensionPackageBuilder
{
    public function build(string $source,string $output,string $serverOrigin='https://search.choko1229.net'): array
    {
        $url=parse_url($serverOrigin);
        if(!is_array($url)||!isset($url['scheme'],$url['host'])
            ||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])
            ||($url['path']??'/')!=='/'||!filter_var($serverOrigin,FILTER_VALIDATE_URL)||!preg_match('/^(?:[A-Za-z0-9.-]+|\[::1\])$/D',$url['host'])
            ||!in_array($url['scheme'],['http','https'],true)
            ||($url['scheme']==='http'&&!in_array(strtolower($url['host']),['localhost','127.0.0.1','[::1]'],true)&&!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+localhost$/iD',$url['host'])))throw new \RuntimeException('INVALID_EXTENSION_SERVER');
        $serverOrigin=$url['scheme'].'://'.strtolower($url['host']).(isset($url['port'])?':'.$url['port']:'');
        $hostPermission=$url['scheme'].'://'.strtolower($url['host']).'/*';
        UpdatePackagePaths::directory($source);
        UpdatePackagePaths::directory(dirname($output));
        $source=realpath($source);$parent=realpath(dirname($output));$name=basename($output);
        if($source===false||$parent===false||!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,79}$/D',$name)
            ||$parent===$source||str_starts_with($parent,$source.DIRECTORY_SEPARATOR))throw new \RuntimeException('INVALID_EXTENSION_PATH');
        $output=$parent.DIRECTORY_SEPARATOR.$name;
        if(file_exists($output)||is_link($output))throw new \RuntimeException('EXTENSION_OUTPUT_EXISTS');
        $version=trim(file_get_contents($source.'/VERSION'));
        if(!preg_match('/^((?:0|[1-9]\d{0,4})\.(?:0|[1-9]\d{0,4})\.(?:0|[1-9]\d{0,4}))(?:-[A-Za-z0-9.-]+)?$/D',$version,$match)
            ||max(array_map('intval',explode('.',$match[1])))>65535)throw new \RuntimeException('INVALID_EXTENSION_VERSION');
        if(!mkdir($output,0700))throw new \RuntimeException('EXTENSION_STORAGE_UNAVAILABLE');
        try{
            $files=[];
            $write=static function(string $path,string $body)use($output,&$files):void{
                $target=$output.'/'.$path;
                if(!is_dir(dirname($target))&&!mkdir(dirname($target),0700,true))throw new \RuntimeException('EXTENSION_STORAGE_UNAVAILABLE');
                if(file_put_contents($target,$body)!==strlen($body)||!chmod($target,0600))throw new \RuntimeException('EXTENSION_STORAGE_UNAVAILABLE');
                $files[$path]=hash('sha256',$body);
            };
            foreach(['js','css','icons','backgrounds'] as $part){
                $assetRoot=$source.'/public/assets/'.$part;UpdatePackagePaths::directory($assetRoot);
                $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($assetRoot,\FilesystemIterator::SKIP_DOTS));
                foreach($iterator as $file){
                    if($file->isLink()||!$file->isFile())throw new \RuntimeException('INVALID_EXTENSION_ASSET');
                    $relative=substr($file->getPathname(),strlen($assetRoot)+1);
                    if(!preg_match('~^[A-Za-z0-9_.-]+(?:[/\\\\][A-Za-z0-9_.-]+)*$~D',$relative)
                        ||!in_array(strtolower($file->getExtension()),['js','css','svg','png','jpg','jpeg','webp','avif','gif','woff','woff2','mp4','webm'],true))throw new \RuntimeException('INVALID_EXTENSION_ASSET');
                    $write('assets/'.$part.'/'.str_replace('\\','/',$relative),file_get_contents($file->getPathname()));
                }
            }
            // No site configuration, authentication material, user storage, PHP or remote executable code.
            $manifest=['manifest_version'=>3,'name'=>'search.choko1229.net','version'=>$match[1],'version_name'=>$version,
                'description'=>'A personal search start page.','chrome_url_overrides'=>['newtab'=>'newtab.html'],
                'permissions'=>['unlimitedStorage'],'host_permissions'=>[$hostPermission],
                'content_security_policy'=>['extension_pages'=>"script-src 'self'; object-src 'none'; base-uri 'none'; frame-src 'none'; connect-src 'self' $serverOrigin;"]];
            $write('manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
            foreach(['ja'=>'newtab.html','en'=>'newtab-en.html'] as $locale=>$page){
                $t=new Translator($source,$locale);$e=View::escape(...);
                $data=['site_name'=>'search.choko1229.net','providers'=>ProviderPresets::client(ProviderPresets::validate(require $source.'/config/providers.php')),
                    'platform'=>['kind'=>'extension','serverOrigin'=>$serverOrigin]];
                ob_start();
                try{require $source.'/app/Views/home.php';$content=ob_get_clean();}catch(\Throwable $error){ob_end_clean();throw $error;}
                ob_start();
                try{require $source.'/extension/layout.php';$html=ob_get_clean();}catch(\Throwable $error){ob_end_clean();throw $error;}
                $write($page,$html);
            }
            $write('assets/js/extension-shell.js',file_get_contents($source.'/extension/shell.js'));
            ksort($files);return ['version'=>$version,'files'=>$files];
        }catch(\Throwable $error){$this->remove($output);throw $error;}
    }
    private function remove(string $path):void
    {
        if(is_link($path))throw new \RuntimeException('INVALID_EXTENSION_PATH');
        if(is_dir($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$this->remove($path.'/'.$name);rmdir($path);}
        elseif(is_file($path))unlink($path);
    }
}
