<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\{Database,Migrator};
use App\Repositories\{AuthRepository,AdminRepository};
use App\Services\DiscordOAuth;
use App\Auth\DeviceAgent;
if (PHP_SAPI!=='cli' || getenv('SEARCH_TEST_MODE')!=='1') exit(1);
$count=0;
$check=static function(bool $ok,string $name) use(&$count):void {
    if (!$ok) throw new RuntimeException($name);
    ++$count; echo "PASS: $name\n";
};
$root=dirname(__DIR__);
$pdo=Database::connect(Config::load($root)->get('database'));
$migrator=new Migrator($pdo,$root.'/database/migrations');
$check($migrator->migrate()===[],'migration repeat safe');
$migration=require $root.'/database/migrations/010_admin_flag.php';
$migration->up($pdo);
$repository=new AuthRepository($pdo);
$adminRepository=new AdminRepository($pdo);
$ids=[];
$cookies=[];
$http=static function(string $path,string $cookie=''):array {
    $context=stream_context_create(['http'=>['header'=>"Accept-Language: en\r\nCookie: $cookie",'ignore_errors'=>true]]);
    $body=file_get_contents('http://127.0.0.1'.$path,false,$context);
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);
    return [(int)$match[1],$body];
};
try {
    foreach (['999999999999999951','999999999999999952'] as $discordId) {
        $id=$repository->upsertIdentity(DiscordOAuth::validateIdentity(['id'=>$discordId,'username'=>'Admin verification']), 'en');
        $ids[]=$id;
        $device=bin2hex(random_bytes(16)); $token=bin2hex(random_bytes(32));
        $repository->createDevice($id,$device,hash('sha256',$token),DeviceAgent::parse('Windows Chrome/120'),time());
        $cookies[]='search_remember='.$device.'.'.$token;
    }
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$ids[0]]);
    $check($adminRepository->isAdministrator($ids[0]),'existing membership default grants access');
    $check(!$adminRepository->isAdministrator($ids[1]),'regular user has no membership');
    foreach (['/admin','/api/admin/dashboard'] as $path) {
        $check($http($path)[0]===401,'guest rejected '.$path);
        $check($http($path,$cookies[1])[0]===403,'regular user rejected '.$path);
        [$status,$body]=$http($path,$cookies[0]);
        $check($status===200,'administrator accepted '.$path);
        if (str_contains($path,'/api/')) {
            $data=json_decode($body,true,512,JSON_THROW_ON_ERROR)['data'];
            $check($data['counts']['users']>0 && is_bool($data['compression']['ffmpeg']),'actual counts and codec flags');
            $check(!str_contains($body,'token_hash') && !str_contains($body,'client_secret'),'dashboard contains no credentials');
        } else $check(str_contains($body,'Admin dashboard'),'English admin page rendered');
    }
    $pdo->prepare('UPDATE administrators SET admin_flag=0 WHERE user_id=?')->execute([$ids[0]]);
    $check($http('/api/admin/dashboard',$cookies[0])[0]===403,'revocation applies to existing login immediately');
    $check($http('/api/admin/dashboard?admin_flag=1&user_id='.$ids[0],$cookies[1].'; admin_flag=1')[0]===403,'client administrator flags cannot grant access');
    $pdo->prepare('UPDATE administrators SET admin_flag=1 WHERE user_id=?')->execute([$ids[0]]);
    $check($http('/api/admin/dashboard',$cookies[0])[0]===200,'membership restoration applies immediately');
    $pdo->prepare('DELETE FROM administrators WHERE user_id=?')->execute([$ids[0]]);
    $check($http('/admin',$cookies[0])[0]===403,'deleted membership rejected');
} finally {
    foreach ($ids as $id) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}
echo "$count admin checks passed.\n";
