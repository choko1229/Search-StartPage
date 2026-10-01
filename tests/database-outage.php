<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1' || getenv('SEARCH_DB_OUTAGE') !== '1') exit(1);
// Run only while the isolated test database is stopped.
$count=0;
foreach (['', 'search_remember='.str_repeat('a',32).'.'.str_repeat('b',64)] as $cookie) {
foreach ([['/',200,'id="query"'],['/api/csrf',200,'csrf_token'],['/api/user',503,'DATABASE_UNAVAILABLE'],['/api/health',503,'DATABASE_UNAVAILABLE']] as [$path,$expected,$text]) {
    $context=stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>10,'header'=>$cookie?'Cookie: '.$cookie:'']]);
    $body=file_get_contents('http://127.0.0.1'.$path,false,$context);
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);
    if((int)$match[1]!==$expected || !str_contains($body,$text) || preg_match('/SQLSTATE|Stack trace|Warning:|Fatal error:/',$body)) throw new RuntimeException('Outage response failed: '.$path);
    $count++;echo "PASS: DB outage $path\n";
}
}
echo "$count database outage assertions passed.\n";
