<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\{UpdateFiles,UpdatePackage,ReleasePackageBuilder};
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=dirname(__DIR__);$directory=sys_get_temp_dir().'/update-files-live-'.bin2hex(random_bytes(8));mkdir($directory,0700);
$configuration=hash_file('sha256',$root.'/config/config.php');$version=trim(file_get_contents($root.'/VERSION'));
$remove=function($path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
try{
    $files=new UpdateFiles();$files->snapshot($root,$directory.'/source.tar');$package=new UpdatePackage();
    $package->verify($directory.'/source.tar',$directory.'/clone',$version);$package->verify($directory.'/source.tar',$directory.'/candidate',$version);
    $clone=$directory.'/clone';$candidate=$directory.'/candidate';
    mkdir($clone.'/storage',0700);file_put_contents($clone.'/storage/user-upload.dat','generated user bytes');file_put_contents($clone.'/config/config.php','generated configuration marker');file_put_contents($clone.'/app/generated-obsolete.php','<?php');
    $files->snapshot($clone,$directory.'/before.tar');$previous=$package->verify($directory.'/before.tar',$directory.'/old-stage',$version);
    file_put_contents($candidate.'/VERSION','999.0.0-dev');file_put_contents($candidate.'/README.md','generated updated readme');file_put_contents($candidate.'/public/generated-new.txt','generated addition');
    (new ReleasePackageBuilder())->build($candidate,$directory.'/next.tar');$manifest=$package->verify($directory.'/next.tar',$directory.'/new-stage','999.0.0-dev');
    $files->replace($directory.'/new-stage',$clone,$manifest,$previous);
    if(file_get_contents($clone.'/VERSION')!=='999.0.0-dev'||file_exists($clone.'/app/generated-obsolete.php')||!is_file($clone.'/public/generated-new.txt'))throw new RuntimeException('Replacement failed');
    $files->restore($directory.'/before.tar',$directory.'/rollback-stage',$clone,$version,$manifest);
    foreach($previous['files'] as $path=>$metadata)if(!hash_equals($metadata['sha256'],hash_file('sha256',$clone.'/'.$path)))throw new RuntimeException('Restoration differs');
    if(file_exists($clone.'/public/generated-new.txt')||file_get_contents($clone.'/config/config.php')!=='generated configuration marker'||file_get_contents($clone.'/storage/user-upload.dat')!=='generated user bytes'||!hash_equals($configuration,hash_file('sha256',$root.'/config/config.php')))throw new RuntimeException('Protected data differs');
    echo json_encode(['status'=>'success','restored_files'=>count($previous['files']),'actual_source_clone'=>true,'added_file_removed'=>true,'deleted_file_recovered'=>true,'protected_markers_preserved'=>true,'actual_configuration_unchanged'=>true],JSON_THROW_ON_ERROR)."\n";
}finally{$remove($directory);}
