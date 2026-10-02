<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);
$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
$migration=require $root.'/database/migrations/009_background_upload_receipts.php';
$migration->up($pdo);$migration->up($pdo);
if((int)$pdo->query('SELECT COUNT(*) FROM background_upload_receipts')->fetchColumn()!==0)throw new RuntimeException('Receipt migration rollback requires an empty test table');
$migration->down($pdo);$migration->down($pdo);$migration->up($pdo);
$again=(new App\Database\Migrator($pdo,$root.'/database/migrations'))->migrate();
if($again!==[])throw new RuntimeException('Unexpected unapplied migrations');
echo "Receipt migration up/up/down/down/up and migration checksum verification passed.\n";
