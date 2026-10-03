<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/autoload.php';
use App\Auth\{OAuthState,DeviceAgent};
use App\Services\DiscordOAuth;
use App\Repositories\AuthRepository;
use App\Database\Database;
use App\Config;
use App\Http\HttpException;

if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1') { exit(1); }
$count=0;
$check=static function(bool $ok,string $name) use (&$count): void {
    if (!$ok) { throw new RuntimeException($name); }
    $count++; echo "PASS: $name\n";
};
$session=[]; $state=OAuthState::issue($session,100);
OAuthState::consume($session,$state,101);
$check(!isset($session['oauth_state']),'state consumed');
foreach ([[$state,102],['wrong',103],[[],104]] as [$value,$now]) {
    try { OAuthState::consume($session,$value,$now); throw new RuntimeException('state accepted'); }
    catch(HttpException $error) { $check($error->status===400,'state replay or malformed rejected'); }
}
$state=OAuthState::issue($session,100);
try { OAuthState::consume($session,$state,700); throw new RuntimeException('expiry accepted'); }
catch(HttpException $error) { $check($error->status===400,'expired state rejected'); }
$check(DeviceAgent::parse('Windows Chrome/120 Edg/120')===['browser'=>'Edge','os'=>'Windows'],'device agent parsing');
$oauth=new DiscordOAuth(new Config(['site'=>['url'=>'https://example.test'],'discord'=>['client_id'=>'123456789012345678','client_secret'=>'synthetic']]));
parse_str(parse_url($oauth->authorizationUrl(str_repeat('a',64)),PHP_URL_QUERY),$query);
$check($query['scope']==='identify' && $query['redirect_uri']==='https://example.test/auth/discord/callback','OAuth scope and fixed callback');
parse_str(parse_url($oauth->authorizationUrl(str_repeat('a',64),true),PHP_URL_QUERY),$apiQuery);
$check($apiQuery['redirect_uri']==='https://example.test/api/auth/discord/callback' && $apiQuery['scope']==='identify','API OAuth uses the JSON callback');
$pdo=Database::connect(Config::load(dirname(__DIR__))->get('database'));
$repository=new AuthRepository($pdo);
$identity=DiscordOAuth::validateIdentity(['id'=>'999999999999999991','username'=>'Auth Test']);
$other=DiscordOAuth::validateIdentity(['id'=>'999999999999999992','username'=>'Other']);
$uid=$repository->upsertIdentity($identity,'en'); $otherId=$repository->upsertIdentity($other,'en');
try {
    $pdo->prepare('INSERT INTO installation_claims (discord_id,created_at) VALUES (?,UTC_TIMESTAMP())')->execute([$identity['id']]);
    $repository->upsertIdentity($identity,'en');
    $admin=$pdo->prepare('SELECT COUNT(*) FROM administrators WHERE user_id=?');$admin->execute([$uid]);
    $check((int)$admin->fetchColumn()===1,'verified reserved identity becomes initial administrator');
    $repository->upsertIdentity($identity,'en');$admin->execute([$uid]);
    $check((int)$admin->fetchColumn()===1,'administrator claim is idempotent');
    $identity['username']='Updated';
    $check($repository->upsertIdentity($identity,'en')===$uid && $repository->user($uid)['discord_username']==='Updated','identity update retains user');
    $device=bin2hex(random_bytes(16)); $second=bin2hex(random_bytes(16)); $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token); $now=time();
    $repository->createDevice($uid,$device,$hash,DeviceAgent::parse('Windows Chrome/120'),$now);
    $repository->createDevice($uid,$second,$hash,DeviceAgent::parse('iPhone Safari/1'),$now);
    $check(count($repository->devices($uid))===2,'multiple devices');
    $check($repository->authenticate($device,str_repeat('0',64),$now)===null,'invalid token rejected');
    $check($repository->authenticate($device,$hash,$now+89*86400)!==null,'long login before expiry');
    $check($repository->authenticate($device,$hash,$now+91*86400)!==null,'rolling expiry extended');
    $check($repository->authenticate($second,$hash,$now+90*86400)===null,'inactive device expires after 90 days');
    $repository->rename($otherId,$device,'Attacker'); $repository->revoke($otherId,$device);
    $check(count($repository->devices($uid))===2 && $repository->devices($uid)[0]['name']!=='Attacker','cross-user device mutation rejected');
    $repository->rename($uid,$device,'Renamed');
    $check($repository->devices($uid)[0]['name']==='Renamed','device rename');
    $stored=$pdo->prepare('SELECT token_hash FROM login_tokens WHERE device_id=?');$stored->execute([$device]);
    $check($stored->fetchColumn()===$hash,'only token hash stored');
    // Restore an ordinary current expiry for HTTP verification.
    $repository->authenticate($device,$hash,$now);
    $headers=['Cookie: search_remember='.$device.'.'.$token,'Accept-Language: en'];
    $context=stream_context_create(['http'=>['header'=>implode("\r\n",$headers),'ignore_errors'=>true]]);
    foreach(['/', '/api/csrf', '/api/search/suggest?q='] as $localPath) {
        $pdo->prepare('UPDATE login_tokens SET expires_at=? WHERE device_id=?')->execute([gmdate('Y-m-d H:i:s',time()+60),$device]);
        $localPage=file_get_contents('http://127.0.0.1'.$localPath,false,$context);
        $statement=$pdo->prepare('SELECT expires_at FROM login_tokens WHERE device_id=?');$statement->execute([$device]);
        $check(str_contains($http_response_header[0],'200') && $statement->fetchColumn()>gmdate('Y-m-d H:i:s',time()+89*86400),"local route $localPath extends rolling login");
    }
    $page=file_get_contents('http://127.0.0.1/account',false,$context);
    $check(str_contains($page,'Updated') && str_contains($page,'(this device)'),'long cookie restores account and current device');
    $check((bool)array_filter($http_response_header,static fn($line)=>str_contains(strtolower($line),'httponly') && str_contains(strtolower($line),'samesite=lax')),'authentication cookie security attributes');
    $cookies=['search_remember'=>$device.'.'.$token];
    foreach($http_response_header as $line) {
        if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$line,$cookie)) { $cookies[$cookie[1]]=$cookie[2]; }
    }
    preg_match('/name="_csrf" value="([a-f0-9]+)"/',$page,$csrf);
    $cookieHeader='Cookie: '.implode('; ',array_map(static fn($key,$value)=>$key.'='.$value,array_keys($cookies),$cookies));
    $api=static function(string $method,string $path,bool $withCsrf=true) use ($cookieHeader,$csrf): array {
        $context=stream_context_create(['http'=>['method'=>$method,'header'=>$cookieHeader.($withCsrf?"\r\nX-CSRF-Token: ".$csrf[1]:''),'ignore_errors'=>true,'follow_location'=>0]]);
        $result=file_get_contents('http://127.0.0.1'.$path,false,$context);
        preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
        return [(int)$status[1],json_decode($result,true,flags:JSON_THROW_ON_ERROR)];
    };
    [$status,$json]=$api('GET','/api/user');
    $check($status===200 && (int)$json['data']['user']['id']===$uid,'current user API');
    [$status,$json]=$api('GET','/api/user/devices');
    $check($status===200 && count($json['data']['devices'])===2 && !str_contains(json_encode($json),'token_hash'),'device list API omits token hashes');
    [$status]=$api('DELETE','/api/user/devices/'.$second,false);
    $check($status===403 && count($repository->devices($uid))===2,'device API requires CSRF');
    [$status]=$api('DELETE','/api/user/devices/invalid');
    $check($status===422,'device API validates identifiers');
    $foreign=bin2hex(random_bytes(16));
    $repository->createDevice($otherId,$foreign,$hash,DeviceAgent::parse('Firefox/1'),$now);
    [$status]=$api('DELETE','/api/user/devices/'.$foreign);
    $check($status===200 && count($repository->devices($otherId))===1,'device API cannot revoke another owner');
    [$status]=$api('DELETE','/api/user/devices/'.$second);
    $check($status===200 && count($repository->devices($uid))===1,'device API revokes owned device');
    $post=static function(string $body) use ($cookieHeader): array {
        $context=stream_context_create(['http'=>['method'=>'POST','header'=>$cookieHeader."\r\nContent-Type: application/x-www-form-urlencoded",'content'=>$body,'ignore_errors'=>true,'follow_location'=>0]]);
        $result=file_get_contents('http://127.0.0.1/auth/logout',false,$context);
        return [$result,$http_response_header];
    };
    [, $responseHeaders]=$post('_csrf=bad');
    $check(str_contains($responseHeaders[0],'403'),'logout requires CSRF');
    [$result,$responseHeaders]=$post(http_build_query(['_csrf'=>$csrf[1]]));
    $check(str_contains($responseHeaders[0],'200') && str_contains($result,'Signed out'),'authenticated logout route');
    $repository->revoke($uid,$device);
    $check($repository->authenticate($device,$hash,$now)===null,'revoked token rejected');
    $page=file_get_contents('http://127.0.0.1/account',false,$context);
    $check(!str_contains($page,'Updated'),'revoked device loses HTTP authentication');
    foreach (['logout','current-device'] as $operation) {
        $freshDevice=bin2hex(random_bytes(16));
        $freshToken=bin2hex(random_bytes(32));
        $repository->createDevice($uid,$freshDevice,hash('sha256',$freshToken),DeviceAgent::parse('Firefox/1'),time());
        $freshCookies=['search_remember'=>$freshDevice.'.'.$freshToken];
        $freshContext=stream_context_create(['http'=>['header'=>'Cookie: search_remember='.$freshCookies['search_remember'],'ignore_errors'=>true]]);
        $freshPage=file_get_contents('http://127.0.0.1/account',false,$freshContext);
        foreach($http_response_header as $line)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$line,$cookie))$freshCookies[$cookie[1]]=$cookie[2];
        preg_match('/name="_csrf" value="([a-f0-9]+)"/',$freshPage,$freshCsrf);
        $freshHeader='Cookie: '.implode('; ',array_map(static fn($key,$value)=>$key.'='.$value,array_keys($freshCookies),$freshCookies));
        $path=$operation==='logout'?'/api/auth/logout':'/api/user/devices/'.$freshDevice;
        $method=$operation==='logout'?'POST':'DELETE';
        if($operation==='logout') {
            $wrongContext=stream_context_create(['http'=>['method'=>'POST','header'=>$freshHeader."\r\nX-CSRF-Token: ".$freshCsrf[1]."\r\nContent-Type: application/json",'content'=>json_encode(['user_id'=>(string)($uid+1)]),'ignore_errors'=>true]]);
            file_get_contents('http://127.0.0.1/api/auth/logout',false,$wrongContext);
            $check(str_contains($http_response_header[0],'403')&&$repository->authenticate($freshDevice,hash('sha256',$freshToken),time())!==null,'logout owner mismatch preserves authenticated device');
        }
        $freshContext=stream_context_create(['http'=>['method'=>$method,'header'=>$freshHeader."\r\nX-CSRF-Token: ".$freshCsrf[1],'ignore_errors'=>true]]);
        $json=json_decode(file_get_contents('http://127.0.0.1'.$path,false,$freshContext),true,flags:JSON_THROW_ON_ERROR);
        $check(str_contains($http_response_header[0],'200') && ($json['data']['logged_out']??false) && (int)$json['data']['user_id']===$uid,"$operation API succeeds with JSON");
        $check((bool)array_filter($http_response_header,static fn($line)=>preg_match('/^Set-Cookie: search_remember=.*Max-Age=0/i',$line)===1),"$operation clears remember cookie");
        $check($repository->authenticate($freshDevice,hash('sha256',$freshToken),time())===null,"$operation revokes DB token");
        $freshContext=stream_context_create(['http'=>['header'=>$freshHeader,'ignore_errors'=>true]]);
        file_get_contents('http://127.0.0.1/api/user',false,$freshContext);
        $check(str_contains($http_response_header[0],'401'),"$operation rejects old cookies");
    }
} finally {
    $pdo->prepare('DELETE FROM installation_claims WHERE discord_id=?')->execute([$identity['id']]);
    $pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$uid,$otherId]);
}
echo "$count auth assertions passed.\n";
