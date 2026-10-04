<?php
declare(strict_types=1);
namespace App\Services;

final class LogMaintenance
{
    private ?string $generation=null;
    public function __construct(private readonly string $root,?string $generation=null) {$this->generation=$generation;}

    public function run(): array
    {
        $access=new UpdateAccess($this->root.'/storage/updates/access');$lease=$access->enter();
        if($lease===null)throw new UpdateAccessPaused();
        try{$generation=$access->generation();if($this->generation!==null&&$generation!==$this->generation)throw new UpdateAccessRestart();$this->generation=$generation;return $this->cleanup();}finally{$lease->release();}
    }
    private function cleanup(): array
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
