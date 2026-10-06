<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);$host=getenv('TEST_EXTENSION_HOST');$vhost=getenv('TEST_EXTENSION_VHOST');
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||$root!=='/tmp/search-extension-web'
    ||!in_array($host,['search-extension-ui-mysql-20261006','search-extension-ui-mariadb-20261006'],true)
    ||!in_array($vhost,['extension-mysql.localhost:8115','extension-mariadb.localhost:8116'],true)
    ||!is_file($root.'/storage/extension-test-only')||is_file($root.'/config/config.php'))throw new RuntimeException('ISOLATED_EXTENSION_SETUP_REQUIRED');
require $root.'/app/autoload.php';
$settings=require $root.'/config/config.example.php';$settings['installed']=true;
$settings['site']['url']='http://'.$vhost;
$settings['database']=['host'=>$host,'port'=>3306,'name'=>'extension_ui','user'=>'extension_test','password'=>getenv('TEST_EXTENSION_PASSWORD')];
$settings['session']=['name'=>'extension_ui_'.(str_contains($host,'mariadb')?'mariadb':'mysql'),'secure'=>false];
$settings['encryption_key']=bin2hex(random_bytes(32));
$settings['weather']['enabled']=false; // No external weather calls during isolated transport verification.
$deadline=time()+60;$pdo=null;
do{try{$pdo=App\Database\Database::connect($settings['database']);break;}catch(PDOException){if(time()>=$deadline)throw new RuntimeException('Dedicated extension DB unavailable');usleep(200000);}}while(true);
if($pdo->query('SHOW TABLES')->fetchAll()!==[])throw new RuntimeException('Dedicated extension schema must be empty');
$migrator=new App\Database\Migrator($pdo,$root.'/database/migrations');
if(count($migrator->migrate())!==17||$migrator->migrate()!==[])throw new RuntimeException('Extension fresh/repeated migrations failed');
file_put_contents($root.'/config/config.php','<?php return '.var_export($settings,true).';');chmod($root.'/config/config.php',0600);
$repository=new App\Repositories\AuthRepository($pdo);
foreach(['999999999999999980'=>'Generated isolated extension primary','999999999999999981'=>'Generated isolated extension other'] as $id=>$name){
    $repository->upsertIdentity(['id'=>(string)$id,'username'=>$name,'display_name'=>null,'avatar'=>null],'ja');
}
if((int)$pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn()!==0)throw new RuntimeException('Extension fixture must not grant administrator privileges');
echo "Dedicated extension configuration, 17 fresh/repeated migrations and two non-admin accounts prepared.\n";
