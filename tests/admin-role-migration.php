<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1') exit(1);
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);
$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
if ((int)$pdo->query('SELECT COUNT(*) FROM administrators WHERE admin_flag=1')->fetchColumn() !== 0) {
    throw new RuntimeException('Empty disposable administrator environment required');
}
$migration=require $root.'/database/migrations/016_admin_roles.php';
$read=static function()use($pdo):array|false {
    $query=$pdo->prepare("SELECT * FROM site_settings WHERE setting_key='admin_roles'");
    $query->execute();return $query->fetch(PDO::FETCH_ASSOC);
};
$count=0;
$check=static function(bool $ok,string $name)use(&$count):void {
    if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";
};
$before=$read();
$pdo->beginTransaction();
try {
    // Migration 016 contains transactional DML only. Restore the exact prior row by rollback.
    $migration->down($pdo);$check($read()===false,'down removes only role coordination setting');
    $migration->up($pdo);$created=$read();
    $check($created!==false&&(int)$created['version']===1&&$created['value_json']==='{}','up initializes empty role setting');
    $pdo->prepare("UPDATE site_settings SET version=77 WHERE setting_key='admin_roles'")->execute();
    $migration->up($pdo);$check((int)$read()['version']===77,'repeat up preserves existing version');
    $migration->down($pdo);$migration->down($pdo);$check($read()===false,'repeat down is safe');
    $migration->up($pdo);$check((int)$read()['version']===1,'up after down restores setting');
}finally{$pdo->rollBack();}
$check($read()===$before,'verification preserves original setting exactly');
echo "$count role migration checks passed.\n";
