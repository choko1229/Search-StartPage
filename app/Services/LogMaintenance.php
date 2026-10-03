<?php
declare(strict_types=1);
namespace App\Services;

final class LogMaintenance
{
    public function __construct(private readonly string $root) {}

    public function run(): array
    {
        $config=\App\Config::load($this->root);
        if(!$config->get('installed'))throw new \RuntimeException('Installation required');
        $pdo=\App\Database\Database::connect($config->get('database'));
        $repository=new \App\Repositories\LogRepository($pdo);
        $files=new FileLogger($this->root.'/storage/logs');
        $result=(new LogRetention($repository,$this->root.'/storage/logs'))->run();
        (new AdminAuditLogger($pdo,$files))->flush();
        $result['recovery']=(new ApplicationLogger($repository,$files,$this->root.'/storage/log-pending'))->recover(1000);
        return $result;
    }
}
