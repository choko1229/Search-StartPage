<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
$hosts=['search-roles-ui-mysql-20261004'=>8109,'search-roles-ui-mariadb-20261004'=>8110];
$host=getenv('TEST_ROLES_UI_HOST');$mode=$argv[1]??'';$root=dirname(__DIR__);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||!isset($hosts[$host])||!in_array($mode,['setup','seed','observe'],true)||!is_file($root.'/storage/roles-ui-test-only'))exit(1);
$settings=require $root.'/config/config.example.php';$settings['installed']=true;
$settings['database']=['host'=>$host,'port'=>3306,'name'=>'roles_ui','user'=>'roles','password'=>getenv('TEST_ROLES_UI_PASSWORD')];
$settings['site']['url']='http://127.0.0.1:'.$hosts[$host];$settings['session']=['name'=>'isolated_roles_ui','secure'=>false];
$deadline=microtime(true)+60;
do{try{$pdo=App\Database\Database::connect($settings['database']);break;}catch(PDOException){if(microtime(true)>$deadline)throw new RuntimeException('Dedicated role DB unavailable');usleep(100000);}}while(true);
if($mode==='setup'){
    if(file_exists($root.'/config/config.php')||$pdo->query('SHOW TABLES')->fetchAll()!==[])throw new RuntimeException('Fresh dedicated role deployment required');
    $migrator=new App\Database\Migrator($pdo,$root.'/database/migrations');if(count($migrator->migrate())!==17||$migrator->migrate()!==[])throw new RuntimeException('Fresh/repeat migrations failed');
    file_put_contents($root.'/config/config.php','<?php return '.var_export($settings,true).';');chmod($root.'/config/config.php',0600);
    echo "Dedicated role UI configuration and all 17 fresh/repeat migrations prepared.\n";exit;
}
if(!file_exists($root.'/config/config.php')||App\Config::load($root)->get('database.host')!==$host)throw new RuntimeException('Dedicated configuration mismatch');
$identities=['999999999999999975'=>'Generated isolated role operator','999999999999999974'=>'Generated isolated role target'];
if($mode==='seed'){
    if((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()!==0||(int)$pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn()!==0)throw new RuntimeException('Empty generated user environment required');
    $auth=new App\Repositories\AuthRepository($pdo);$ids=[];
    foreach($identities as $discord=>$name)$ids[]=$auth->upsertIdentity(['id'=>(string)$discord,'username'=>$name,'display_name'=>null,'avatar'=>null],'ja');
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$ids[0]]);
    $device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($ids[0],$device,hash('sha256',$token),App\Auth\DeviceAgent::parse('Windows Chrome/120'),time());
    if(!is_dir($root.'/public/_test'))mkdir($root.'/public/_test',0700);
    $source='<?php if(getenv("SEARCH_TEST_MODE")!=="1"||getenv("SEARCH_LOCAL_DEVELOPMENT")!=="1"||!is_file(dirname(__DIR__,2)."/storage/roles-ui-test-only")){http_response_code(404);exit;}setcookie("search_remember",'.var_export($device.'.'.$token,true).',["path"=>"/","httponly"=>true,"samesite"=>"Lax"]);header("Location: /admin/users",true,303);';
    file_put_contents($root.'/public/_test/roles-login.php',$source);chmod($root.'/public/_test/roles-login.php',0600);
    echo "Generated isolated role operator and regular target prepared.\n";exit;
}
$query=$pdo->prepare('SELECT u.discord_username,COALESCE(a.admin_flag,0) AS admin_flag FROM users u LEFT JOIN administrators a ON a.user_id=u.id WHERE u.discord_id IN (?,?) ORDER BY u.id');$query->execute(array_keys($identities));
$roles=$query->fetchAll(PDO::FETCH_ASSOC);foreach($roles as &$row)$row['admin_flag']=(int)$row['admin_flag'];unset($row);
$query=$pdo->prepare("SELECT context_json,file_written FROM log_entries WHERE error_code='ADMIN_ROLE_CHANGED' AND user_id=(SELECT id FROM users WHERE discord_id=?) ORDER BY id");$query->execute(['999999999999999975']);$audits=$query->fetchAll(PDO::FETCH_ASSOC);
$query=$pdo->prepare('SELECT id FROM users WHERE discord_id=?');$query->execute(['999999999999999974']);$target=(int)$query->fetchColumn();$version=(new App\Repositories\AdminRoleRepository($pdo))->version();
$verified=count($audits)===2;
foreach($audits as $index=>$audit){$context=json_decode($audit['context_json'],true,flags:JSON_THROW_ON_ERROR);$verified=$verified&&$context===['target_user_id'=>$target,'before'=>$index===1,'after'=>$index===0,'version'=>$version-1+$index]&&(int)$audit['file_written']===1;}
echo json_encode(['generated_roles'=>$roles,'role_change_audit_count'=>count($audits),'audit_pair_and_file_delivery_verified'=>$verified,'version'=>$version],JSON_THROW_ON_ERROR)."\n";
