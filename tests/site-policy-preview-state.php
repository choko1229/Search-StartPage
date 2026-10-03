<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
$repository=new App\Repositories\SitePolicyRepository($pdo);$state=new App\Services\PolicyState($root.'/storage/policy');
$backup=$root.'/storage/policy/preview-original.json';$mode=$argv[1]??'';
if(!in_array($mode,['stop','restore'],true))throw new RuntimeException('Specify stop or restore');
if($mode==='stop'&&file_exists($backup))throw new RuntimeException('Restore the existing preview first');
if($mode==='restore'&&!file_exists($backup))throw new RuntimeException('No preview to restore');
$initial=$repository->read();
if($mode==='stop'){
    $handle=fopen($backup,'x');if(!$handle)throw new RuntimeException('Cannot preserve policy');
    try{$encoded=json_encode($initial['policy'],JSON_THROW_ON_ERROR);if(fwrite($handle,$encoded)!==strlen($encoded))throw new RuntimeException('Incomplete backup');}finally{fclose($handle);}chmod($backup,0600);
    $policy=$initial['policy'];$policy['flags']['external_suggestions']=false;$policy['flags']['favorite_metadata']=false;
}else $policy=App\Services\SitePolicy::validate(json_decode(file_get_contents($backup),true,32,JSON_THROW_ON_ERROR));
$actor=(new App\Repositories\AuthRepository($pdo))->upsertIdentity(['id'=>'999999999999999960','username'=>'Policy UI verification','display_name'=>null,'avatar'=>null],'ja');
try{$repository->update($policy,$initial['version'],$actor,$state);if($mode==='restore')unlink($backup);}
finally{$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$actor]);}
echo "Preview policy $mode applied.\n";
