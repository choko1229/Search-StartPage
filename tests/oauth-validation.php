<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\DiscordOAuth;
use App\Http\HttpException;
$count=0;
foreach ([[], ['access_token'=>[], 'token_type'=>'Bearer'], ['access_token'=>'test','token_type'=>[]], ['access_token'=>"test\r\nInjected: true",'token_type'=>'Bearer'], ['access_token'=>str_repeat('a',4097),'token_type'=>'Bearer'], ['access_token'=>'test','token_type'=>'Basic']] as $payload) {
    try {DiscordOAuth::validateAccessToken($payload);throw new RuntimeException('Invalid token accepted');}
    catch(HttpException $e){if($e->status!==502)throw $e;$count++;}
}
if(DiscordOAuth::validateAccessToken(['access_token'=>'synthetic-valid','token_type'=>'bearer'])!=='synthetic-valid')throw new RuntimeException('Valid token rejected');
$count++;
foreach ([[], ['id'=>[], 'username'=>'x'], ['id'=>'123', 'username'=>'x'], ['id'=>'999999999999999991','username'=>[]]] as $payload) {
    try {DiscordOAuth::validateIdentity($payload);throw new RuntimeException('Invalid identity accepted');}
    catch(HttpException $e){if($e->status!==502)throw $e;$count++;}
}
echo "$count OAuth response validation assertions passed.\n";
