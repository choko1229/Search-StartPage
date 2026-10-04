<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);$host=getenv('TEST_PRESET_OUTAGE_HOST');$mode=$argv[1]??'';
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'
    ||!preg_match('/^search-preset-outage-(mysql|mariadb)-[123]-20261004$/D',(string)$host)
    ||$root!=='/tmp/search-preset-outage-source'||!is_file($root.'/storage/preset-outage-test-only')
    ||!in_array($mode,['prepare','outage','recovered'],true))exit(1);
$settings=require $root.'/config/config.example.php';$settings['installed']=true;
$settings['database']=['host'=>$host,'port'=>3306,'name'=>'preset_outage','user'=>'preset','password'=>getenv('TEST_PRESET_OUTAGE_PASSWORD')];
$settings['site']['url']='http://127.0.0.1:18091';$settings['session']=['name'=>'isolated_preset_outage','secure'=>false];
$connect=static fn()=>App\Database\Database::connect($settings['database']);
$count=0;$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$expected=App\Services\ProviderPresets::defaults();$expected['web'][0]['name']='Generated persisted preset';
$expected=App\Services\ProviderPresets::validate($expected);$client=App\Services\ProviderPresets::client($expected);
$defaults=App\Services\ProviderPresets::client(App\Services\ProviderPresets::defaults());
$cache=$root.'/storage/presets/presets.json';$process=null;$pipes=[];
$http=static function(string $path):array{
    $body=file_get_contents('http://127.0.0.1:18091'.$path,false,stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>15]]));
    preg_match('/^HTTP\/\S+\s+(\d{3})(?:\s|$)/',$http_response_header[0]??'',$match);
    if(!is_string($body)||!isset($match[1]))throw new RuntimeException('Test HTTP unavailable');
    return [(int)$match[1],$body];
};
$catalog=static function(array $value,string $label)use($http,$check):void{
    [$status,$body]=$http('/api/provider-presets');$json=json_decode($body,true,32,JSON_THROW_ON_ERROR);
    $check($status===200&&($json['data']['presets']??null)===$value,$label);
    $check(!preg_match('/Warning:|Notice:|Fatal error:|Stack trace:|SQLSTATE|TEST_PRESET_OUTAGE_PASSWORD/',$body),'API does not expose diagnostics');
};
try{
    if($mode==='prepare'){
        if(file_exists($root.'/config/config.php'))throw new RuntimeException('Fresh configuration required');
        $deadline=microtime(true)+60;do{try{$pdo=$connect();break;}catch(PDOException){if(microtime(true)>$deadline)throw new RuntimeException('Dedicated DB unavailable');usleep(100000);}}while(true);
        $check($pdo->query('SHOW TABLES')->fetchAll()===[],'fresh isolated schema');
        $migrator=new App\Database\Migrator($pdo,$root.'/database/migrations');
        $check(count($migrator->migrate())===17&&$migrator->migrate()===[],'all migrations fresh and repeat');
        $pdo->prepare('INSERT INTO users(id,discord_id,discord_username,locale,created_at,updated_at) VALUES(1,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute(['999999999999999972','Generated preset author','en']);
        $repository=new App\Repositories\ProviderPresetRepository($pdo);$initial=$repository->read();
        $saved=$repository->update($expected,$initial['version'],1,new App\Services\PresetState($root.'/storage/presets'));
        $check($saved['version']===$initial['version']+1,'generated catalog stored with audit');
        file_put_contents($root.'/config/config.php','<?php return '.var_export($settings,true).';');chmod($root.'/config/config.php',0600);
    }elseif($mode==='outage'){
        try{$connect();throw new RuntimeException('DB is still reachable');}catch(PDOException){$check(true,'actual dedicated DB outage observed');}
    }else{
        $deadline=microtime(true)+60;do{try{$pdo=$connect();break;}catch(PDOException){if(microtime(true)>$deadline)throw new RuntimeException('Restarted DB unavailable');usleep(100000);}}while(true);
        $check((new App\Repositories\ProviderPresetRepository($pdo))->read()['presets']===$expected,'DB restart preserves generated admin catalog');
        $check((int)$pdo->query("SELECT COUNT(*) FROM log_entries WHERE error_code='PROVIDER_PRESETS_CHANGED'")->fetchColumn()===1,'outage and reads create no duplicate edit audit');
    }
    $process=proc_open([PHP_BINARY,'-d','display_errors=0','-S','127.0.0.1:18091','-t',$root.'/public',$root.'/public/index.php'],[0=>['pipe','r'],1=>['file',$root.'/storage/preset-server.log','a'],2=>['file',$root.'/storage/preset-server.log','a']],$pipes,$root);
    if(!is_resource($process))throw new RuntimeException('Test server unavailable');fclose($pipes[0]);
    $deadline=microtime(true)+5;do{$socket=@fsockopen('127.0.0.1',18091,$socketCode,$socketError,.1);if(is_resource($socket)){fclose($socket);break;}if(microtime(true)>$deadline)throw new RuntimeException('Test server not ready');usleep(10000);}while(true);
    if($mode==='prepare'){
        $catalog($client,'warm snapshot serves edited catalog');
        foreach(['{broken','{"web":[],"ai":[]}'] as $bad){file_put_contents($cache,$bad);$catalog($client,'broken or invalid snapshot repaired from DB');$check(json_decode(file_get_contents($cache),true)===$expected,'repaired snapshot matches DB');}
        $check((fileperms($cache)&0077)===0,'repaired cache remains private');
    }elseif($mode==='outage'){
        $catalog($client,'warm snapshot keeps edited catalog during DB stop');
        unlink($cache);$catalog($defaults,'missing snapshot and stopped DB use packaged defaults');
        $check(!file_exists($cache),'temporary defaults are not persisted over custom catalog');
        file_put_contents($cache,'{broken');chmod($cache,0600);$catalog($defaults,'broken snapshot and stopped DB use packaged defaults');
    }else{
        $catalog($client,'real HTTP recovers edited catalog after DB restart');
        $check(json_decode(file_get_contents($cache),true)===$expected,'recovered snapshot matches persistent catalog');
    }
    [$status,$home]=$http('/');$check($status===200&&str_contains($home,$mode==='outage'?$defaults['web'][0]['name']:'Generated persisted preset'),'home receives current fallback or recovered catalog');
    $check(!preg_match('/Warning:|Notice:|Fatal error:|Stack trace:|SQLSTATE/',$home),'home does not expose outage diagnostics');
    echo "$count preset $mode checks passed.\n";
}finally{if(is_resource($process)){proc_terminate($process);proc_close($process);}}
