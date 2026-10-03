<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\Database;
use App\Repositories\LogRepository;
use App\Services\{ApplicationLogger,FileLogger};
if(PHP_SAPI!=='cli' || getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=dirname(__DIR__);$pdo=Database::connect(Config::load($root)->get('database'));
$count=0;$events=[];
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$http=static function(string $path):array {
    $body=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>['ignore_errors'=>true,'header'=>'Accept-Language: en']]));
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    return [(int)$status[1],$body];
};
$configPath=$root.'/config/config.php';$original=file_get_contents($configPath);
$publish=static function(string $contents)use($root,$configPath):void {
    $temporary=tempnam($root.'/config','.outage-test-');
    if($temporary===false)throw new RuntimeException('Test configuration unavailable');
    try{if(file_put_contents($temporary,$contents)!==strlen($contents)||!chmod($temporary,0600)||!rename($temporary,$configPath))throw new RuntimeException('Test configuration publication failed');}
    finally{if(is_file($temporary))unlink($temporary);}
};
try {
    // This test endpoint exists only in this dedicated test container.
    foreach(['exception','warning'] as $mode){
        $before=(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM log_entries')->fetchColumn();
        [$status,$body]=$http('/_test/php-errors-preview.php?mode='.$mode);
        $check($status===500 && !str_contains($body,'secret-marker') && !str_contains($body,'Stack trace') && !str_contains($body,'Warning:'),'real PHP '.$mode.' hidden from page');
        $query=$pdo->prepare('SELECT event_id,type,file_written,context_json FROM log_entries WHERE id>?');$query->execute([$before]);$rows=$query->fetchAll(PDO::FETCH_ASSOC);
        $check(count($rows)===1 && $rows[0]['type']==='php_error' && (int)$rows[0]['file_written']===1,'real PHP '.$mode.' delivered to DB');$events[]=$rows[0]['event_id'];
        $check(!str_contains($rows[0]['context_json'],'secret-marker'),'real PHP '.$mode.' omits exception message');
    }
    // Only the dedicated app's development connection is changed. The DB and
    // all existing data stay running. The original config is always restored.
    $settings=require $configPath;$settings['database']['host']='127.0.0.1';$settings['database']['port']=1;
    $publish("<?php\ndeclare(strict_types=1);\nreturn ".var_export($settings,true).";\n");
    try {Database::connect(Config::load($root)->get('database'));throw new RuntimeException('Unreachable fixture connected');}
    catch(PDOException){$check(true,'fixture connection really fails');}
    // Let the PHP workers observe the atomically replaced configuration; do
    // not restart the server or mistake an old configuration for an outage.
    $deadline=microtime(true)+8;
    do {[$healthStatus]=$http('/api/health');if($healthStatus===503)break;usleep(250000);}while(microtime(true)<$deadline);
    $check($healthStatus===503,'HTTP worker observes the unreachable development connection');
    $check($http('/')[0]===200,'normal guest local UI remains available with unreachable DB');
    $pendingBefore=glob($root.'/storage/log-pending/*.json') ?: [];
    [$status,$body]=$http('/api/user');
    $observedCode=json_decode($body,true)['error']['code'] ?? null;
    $check($status===503 && $observedCode==='DATABASE_UNAVAILABLE' && !str_contains($body,'password') && !str_contains($body,'Stack trace'),'actual connection failure returns safe API error: '.json_encode(['status'=>$status,'code'=>$observedCode]));
    $newPending=array_values(array_diff(glob($root.'/storage/log-pending/*.json') ?: [],$pendingBefore));
    $check(count($newPending)===1,'actual connection failure leaves one durable queued event');
    $entry=json_decode(file_get_contents($newPending[0]),true,512,JSON_THROW_ON_ERROR);$events[]=$entry['event_id'];
    $check($entry['file_written']===true && $entry['error_code']==='DATABASE_UNAVAILABLE','actual outage event already delivered to file');
    $publish($original);
    $deadline=microtime(true)+8;
    do {[$healthStatus]=$http('/api/health');if($healthStatus===200)break;usleep(250000);}while(microtime(true)<$deadline);
    $check($healthStatus===200,'HTTP worker observes restored configuration');
    $logger=new ApplicationLogger(new LogRepository($pdo),new FileLogger($root.'/storage/logs'),$root.'/storage/log-pending');
    $result=$logger->recover();
    $query=$pdo->prepare('SELECT COUNT(*) FROM log_entries WHERE event_id=?');$query->execute([$entry['event_id']]);
    $check($result['delivered']>=1 && (int)$query->fetchColumn()===1 && !is_file($newPending[0]),'actual outage queue imported after connection restored');
    $check($http('/api/health')[0]===200,'original development connection restored');
    $file=file_get_contents($root.'/storage/logs/'.gmdate('Y-m-d').'.jsonl');
    $check(!str_contains($file,'generated-php-warning-secret-marker') && !str_contains($file,'generated-php-exception-secret-marker'),'real PHP messages excluded from file');
}finally{
    $publish($original);
    foreach($events as $id)$pdo->prepare('DELETE FROM log_entries WHERE event_id=?')->execute([$id]);
}
echo "$count PHP and outage log checks passed.\n";
