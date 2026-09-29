<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1') { exit(1); }
$cookies=[];
$request=static function(string $method,string $path,array $data=[]) use (&$cookies): array {
    $headers=['Content-Type: application/x-www-form-urlencoded','Accept-Language: en'];
    if($cookies) $headers[]='Cookie: '.implode('; ',array_map(static fn($key,$value)=>$key.'='.$value,array_keys($cookies),$cookies));
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>http_build_query($data),'ignore_errors'=>true,'follow_location'=>0]]);
    $body=file_get_contents('http://127.0.0.1'.$path,false,$context);
    foreach($http_response_header as $line) if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$line,$match)) $cookies[$match[1]]=$match[2];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    return [(int)$status[1],$body];
};
$count=0;
$check=static function(bool $ok,string $label) use (&$count): void { if(!$ok) throw new RuntimeException($label); $count++; echo "PASS: $label\n"; };
[$status,$body]=$request('GET','/account');
$check($status===200 && str_contains($body,'requires configuration'),'unconfigured OAuth has honest account UI');
[$status]=$request('POST','/auth/discord'); $check($status===403,'OAuth initiation rejects missing CSRF');
[$status]=$request('GET','/auth/discord/callback?state%5B%5D=bad&code=bad'); $check($status===400,'callback rejects array state');
[$status]=$request('GET','/auth/discord/callback?state='.str_repeat('a',64).'&code=bad'); $check($status===400,'callback rejects unsolicited state');
[$status,$body]=$request('GET','/api/csrf');
$csrf=json_decode($body,true,flags:JSON_THROW_ON_ERROR)['data']['csrf_token'];
[$status]=$request('POST','/auth/logout',['_csrf'=>$csrf]); $check($status===401,'anonymous logout rejected');
[$status]=$request('POST','/account/device',['_csrf'=>$csrf,'device_id'=>str_repeat('a',32),'action'=>'revoke']); $check($status===401,'anonymous device mutation rejected');
[$status]=$request('GET','/auth/logout'); $check($status===405,'GET cannot log out');
echo "$count HTTP security assertions passed.\n";
