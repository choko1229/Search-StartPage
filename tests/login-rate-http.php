<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('SEARCH_TEST_MODE')!=='1')exit(1);
// Run last: this deliberately exhausts the loopback peer's login allowance.
$status=0;
for($attempt=0;$attempt<21;$attempt++){
    $body=file_get_contents('http://127.0.0.1/api/auth/discord',false,stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0]]));
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);$status=(int)$match[1];
    if($status===429)break;
}
if($status!==429 || (json_decode($body,true)['error']['code']??'')!=='RATE_LIMITED')throw new RuntimeException('HTTP login limit missing');
echo "PASS: HTTP login attempts are limited\n";
$body=file_get_contents('http://127.0.0.1/api/search/suggest?q=',false,stream_context_create(['http'=>['ignore_errors'=>true]]));
if(!str_contains($http_response_header[0],'200') || !(json_decode($body,true)['success']??false))throw new RuntimeException('Search incorrectly rate limited');
echo "PASS: search remains available\n";
