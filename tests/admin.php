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
$storageBefore=$adminRepository->storageTotals();
$ids=[];
$cookies=[];
$savedPolicy=null;
$http=static function(string $path,string $cookie='',string $locale='en'):array {
    $context=stream_context_create(['http'=>['header'=>"Accept-Language: $locale\r\nCookie: $cookie",'ignore_errors'=>true]]);
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
    foreach (['/admin/users','/admin/storage','/api/admin/users','/api/admin/storage'] as $path) {
        $check($http($path)[0]===401,'guest rejected '.$path);
        $check($http($path,$cookies[1])[0]===403,'regular user rejected '.$path);
        $check($http($path,$cookies[0])[0]===200,'administrator accepted '.$path);
    }
    $pdo->prepare('UPDATE users SET discord_username=? WHERE id=?')->execute(['Admin verification <img onerror=alert(1)>%_',$ids[1]]);
    $pdo->prepare("INSERT INTO backgrounds(id,user_id,name,type,source_type,file_size,settings_json,deleted,created_at,updated_at) VALUES ('admin-check-active',?,'Generated','image','url',37,'{}',0,UTC_TIMESTAMP(),UTC_TIMESTAMP()),('admin-check-deleted',?,'Generated','image','url',100,'{}',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$ids[1],$ids[1]]);
    $pdo->prepare("INSERT INTO backgrounds(id,user_id,name,type,source_type,file_size,settings_json,deleted,created_at,updated_at) VALUES ('admin-check-sort',?,'Generated','image','url',60,'{}',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$ids[0]]);
    foreach (['users','storage'] as $section) {
        [$status,$body]=$http('/api/admin/'.$section.'?q=99999999999999995&page_size=1',$cookies[0]);
        $data=json_decode($body,true,512,JSON_THROW_ON_ERROR)['data'];
        $check($status===200 && $data['total']===2 && count($data['items'])===1,'filtered paginated '.$section);
        $check($data['items'][0]['id']===$ids[1] && $data['items'][0]['background_bytes']===37 && $data['items'][0]['background_count']===1,'deleted backgrounds excluded '.$section);
        if($section==='storage'){
            $check($data['items'][0]['stored_background_bytes']===137&&$data['items'][0]['archived_background_bytes']===100&&$data['items'][0]['stored_background_count']===2,'archived bytes retained and total usage determines sorting');
            $summary=$data['storage_summary'];
            $check($summary['stored_background_bytes']===$storageBefore['stored_background_bytes']+197,'storage total includes every account despite search filter');
            $check($summary['active_background_bytes']===$storageBefore['active_background_bytes']+97&&$summary['archived_background_bytes']===$storageBefore['archived_background_bytes']+100,'global storage breakdown is exact');
            $check($summary['stored_background_count']===$storageBefore['stored_background_count']+3,'stored count includes archived backgrounds');
            $limit=$summary['limit_bytes'];
            $check(($limit===null||is_int($limit)&&$limit>0)&&$data['items'][0]['over_limit']===($limit!==null&&137>$limit),'quota projection and over-limit state');
            $policyRepository=new App\Repositories\SitePolicyRepository($pdo);
            $savedPolicy=$policyRepository->read()['policy'];$limited=$savedPolicy;$limited['limits']['background_max_bytes']=100;
            $policyState=new App\Services\PolicyState($root.'/storage/policy');
            $policyRepository->update($limited,$policyRepository->read()['version'],$ids[0],$policyState);
            [, $limitedBody]=$http('/api/admin/storage?q=999999999999999952',$cookies[0]);
            $limitedData=json_decode($limitedBody,true)['data'];
            $check($limitedData['storage_summary']['limit_bytes']===100&&$limitedData['items'][0]['over_limit']===true&&$limitedData['items'][0]['stored_background_bytes']===137,'lowered quota flags actual stored usage without deleting data');
            [, $limitedHtml]=$http('/admin/storage?q=999999999999999952',$cookies[0]);
            $check(str_contains($limitedHtml,'Above the current limit; existing files are preserved.'),'English over-limit explanation');
            $policyRepository->update($savedPolicy,$policyRepository->read()['version'],$ids[0],$policyState);$savedPolicy=null;
        }
        [, $body]=$http('/api/admin/'.$section.'?q=99999999999999995&page_size=1&page=2',$cookies[0]);
        $check(json_decode($body,true)['data']['items'][0]['id']===$ids[0],'second page '.$section);
        [, $body]=$http('/api/admin/'.$section.'?q='.rawurlencode('%_'),$cookies[0]);
        $check(json_decode($body,true)['data']['total']===1,'wildcards treated literally '.$section);
        [, $body]=$http('/api/admin/'.$section.'?q='.rawurlencode("' OR 1=1 --"),$cookies[0]);
        $check(json_decode($body,true)['data']['total']===0,'SQL injection treated literally '.$section);
        [, $body]=$http('/admin/'.$section.'?q=999999999999999952',$cookies[0]);
        $check(str_contains($body,'&lt;img onerror=alert(1)&gt;') && !str_contains($body,'<img onerror=alert(1)>'),'user HTML escaped '.$section);
        [, $body]=$http('/admin/'.$section,$cookies[0],'ja');
        $check(str_contains($body,$section==='users' ? 'ユーザー管理' : '容量管理') && str_contains($body,'lang="ja"'),'Japanese listing '.$section);
        if($section==='storage')$check(str_contains($body,'アーカイブ背景容量')&&str_contains($body,'/admin/policy'),'Japanese storage breakdown and limit editing link');
        [, $body]=$http('/admin/'.$section.'?q='.rawurlencode('"><img onerror=alert(1)>'),$cookies[0]);
        $check(!str_contains($body,'"><img onerror=alert(1)>') && str_contains($body,'&quot;&gt;&lt;img'),'search input escaped '.$section);
        foreach (['page=0','page=-1','page=1000001','page[]=1','page_size=101','page_size=1.5','q[]=x','q='.str_repeat('x',101),'q=%00','q=%FF'] as $invalid) {
            $check($http('/api/admin/'.$section.'?'.$invalid,$cookies[0])[0]===422,'invalid listing rejected '.$section.' '.$invalid);
        }
    }
    $pdo->prepare('DELETE FROM administrators WHERE user_id=?')->execute([$ids[0]]);
    $check($http('/admin',$cookies[0])[0]===403,'deleted membership rejected');
} finally {
    if($savedPolicy!==null)$policyRepository->update($savedPolicy,$policyRepository->read()['version'],$ids[0],$policyState);
    foreach ($ids as $id) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}
echo "$count admin checks passed.\n";
