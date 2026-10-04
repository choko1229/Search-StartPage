<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||getenv('SEARCH_LOCAL_DEVELOPMENT')!=='1')exit(1);
$root=dirname(__DIR__);$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));$path=$root.'/storage/updates/checks/check.json';
(new App\Services\UpdateAccess($root.'/storage/updates/access'))->exclusive(static function()use($pdo,$path):void{
    $counts=static fn()=>[$pdo->query('SELECT COUNT(*) FROM log_entries')->fetchColumn(),$pdo->query('SELECT COUNT(*) FROM statistics_events')->fetchColumn()];$before=$counts();$hash=is_file($path)?hash_file('sha256',$path):null;
    echo "HOLD_READY\n";flush();sleep(45);clearstatcache(true,$path);
    if($counts()!==$before||(is_file($path)?hash_file('sha256',$path):null)!==$hash)throw new RuntimeException('Worker wrote during update stop');
    echo "PASS: actual restarted workers changed no DB counts or update metadata during stop.\n";
});
echo "PASS: test update stop released.\n";
