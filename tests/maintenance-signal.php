<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\MaintenanceState;
if (PHP_SAPI!=='cli' || getenv('SEARCH_TEST_MODE')!=='1') exit(1);
$directory=sys_get_temp_dir().'/maintenance-signal-'.bin2hex(random_bytes(8));
$signal=new MaintenanceState($directory);
$count=0;
$check=static function(bool $ok,string $name)use(&$count):void {if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
try {
    $check($signal->active(),'missing signal requires authoritative resolution');
    $signal->synchronized(fn()=>$signal->publish(false));
    $check(!$signal->active(),'normal operation reads without DB');
    $signal->synchronized(fn()=>$signal->publish(true));
    $check($signal->active(),'enabled signal survives a new instance');
    $check((new MaintenanceState($directory))->active(),'enabled reload remains active');
    file_put_contents($directory.'/maintenance.json','broken');
    $check($signal->active(),'corrupt signal never grants public access');
    file_put_contents($directory.'/maintenance.json','{"enabled":0}');
    $check($signal->active(),'nonboolean false-like value requires authoritative resolution');
    try {$signal->synchronized(static function():never {throw new RuntimeException('Generated failure');});}catch(RuntimeException){}
    $signal->synchronized(fn()=>$signal->publish(false));
    $check(!$signal->active(),'lock released after failed operation');
    $check(count(glob($directory.'/maintenance-*'))===0,'atomic publication leaves no temporary files');
} finally {
    foreach (['maintenance.json','maintenance.lock'] as $file) if(is_file($directory.'/'.$file))unlink($directory.'/'.$file);
    if(is_dir($directory))rmdir($directory);
}
echo "$count maintenance signal checks passed.\n";
