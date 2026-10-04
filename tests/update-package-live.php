<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=dirname(__DIR__);$directory=sys_get_temp_dir().'/search-live-package-'.bin2hex(random_bytes(8));mkdir($directory,0700);
$stage=$directory.'/stage';$archive=$directory.'/release.tar';$before=hash_file('sha256',$root.'/config/config.php');
$run=static function(array $command):string{
    $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Verification process unavailable');
    fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Verification process failed');return $stdout;
};
try{
    $built=(new App\Services\ReleasePackageBuilder())->build($root,$archive);
    $manifest=(new App\Services\UpdatePackage())->verify($archive,$stage,trim(file_get_contents($root.'/VERSION')),true);
    $names=array_filter(explode("\n",trim($run(['tar','-tf',$archive]))));$expected=['release-manifest.json',...array_keys($manifest['files'])];sort($names);sort($expected);
    if($names!==$expected)throw new RuntimeException('Independent tar listing differs');
    $php=0;foreach(array_keys($manifest['files']) as $path){
        if(str_ends_with($path,'.php'))++$php;
        if(!hash_equals($manifest['files'][$path]['sha256'],hash_file('sha256',$stage.'/'.$path)))throw new RuntimeException('Staged file changed');
    }
    if(!hash_equals($before,hash_file('sha256',$root.'/config/config.php'))||is_file($stage.'/config/config.php')||is_dir($stage.'/storage')||is_dir($stage.'/tests')||is_dir($stage.'/public/_test'))throw new RuntimeException('Protected data included or changed');
    echo json_encode(['status'=>'success','files'=>$built['files'],'bytes'=>$built['bytes'],'php_syntax_checked'=>$php,'independent_tar_listing'=>true,'configuration_unchanged'=>true,'protected_data_excluded'=>true],JSON_THROW_ON_ERROR)."\n";
}finally{
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $file){if($file->isLink())throw new RuntimeException('Unexpected verification link');if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($directory);
}
