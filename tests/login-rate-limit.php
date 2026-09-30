<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/autoload.php';
use App\Http\{Request, Response, HttpException};
use App\Middleware\LoginRateLimit;
$directory = sys_get_temp_dir() . '/search-rate-test-' . bin2hex(random_bytes(8));
$limit = new LoginRateLimit($directory, 2, 60);
$next = static fn(Request $r): Response => Response::json(['ok'=>true]);
$request = new Request('GET','/api/auth/discord',server:['REMOTE_ADDR'=>'192.0.2.1']);
$count=0;
$check=static function(bool $ok,string $label) use (&$count): void {if(!$ok)throw new RuntimeException($label);$count++;echo "PASS: $label\n";};
try {
    $check($limit($request,$next)->status===200,'first attempt');
    $check($limit($request,$next)->status===200,'attempt at limit');
    try {$limit(new Request('GET','/auth/discord/callback',server:['REMOTE_ADDR'=>'192.0.2.1','HTTP_X_FORWARDED_FOR'=>'192.0.2.99']),$next);throw new RuntimeException('not limited');}
    catch(HttpException $e){$check($e->status===429,'aliases share limit and forwarding cannot bypass');}
    $check($limit(new Request('GET','/api/auth/discord',server:['REMOTE_ADDR'=>'192.0.2.2']),$next)->status===200,'independent peer');
    $file=$directory.'/login.json';$raw=file_get_contents($file);
    $check(!str_contains($raw,'192.0.2.'),'IP not stored in plain text');
    $rows=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);
    foreach($rows as &$row)$row['until']=time()-1;unset($row);
    file_put_contents($file,json_encode($rows,JSON_THROW_ON_ERROR));
    $check($limit($request,$next)->status===200,'expired bucket resets');
    $check(count(json_decode(file_get_contents($file),true))===1,'expired peers removed');
} finally {if(is_file($directory.'/login.json'))unlink($directory.'/login.json');if(is_dir($directory))rmdir($directory);}
echo "$count login rate assertions passed.\n";
