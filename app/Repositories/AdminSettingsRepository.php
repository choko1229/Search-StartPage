<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Http\HttpException;

final class AdminSettingsRepository
{
    public function __construct(private readonly \PDO $pdo,private readonly \App\Services\MaintenanceState $signal) {}

    public function maintenance(): array
    {
        $query=$this->pdo->prepare("SELECT value_json,version FROM site_settings WHERE setting_key='maintenance'");
        $query->execute();
        $row=$query->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new HttpException(503,'ADMIN_SETTINGS_UNAVAILABLE');
        $enabled=json_decode($row['value_json'],true,512,JSON_THROW_ON_ERROR);
        if (!is_bool($enabled)) throw new HttpException(503,'ADMIN_SETTINGS_UNAVAILABLE');
        return ['enabled'=>$enabled,'version'=>(int)$row['version']];
    }

    /** Settings and the DB audit commit together; stale editors cannot overwrite. */
    public function setMaintenance(bool $enabled,int $expectedVersion,int $actorId): array
    {
        return $this->signal->synchronized(fn():array=>$this->changeMaintenance($enabled,$expectedVersion,$actorId));
    }
    private function changeMaintenance(bool $enabled,int $expectedVersion,int $actorId): array
    {
        $this->pdo->beginTransaction();
        try {
            $query=$this->pdo->prepare("SELECT value_json,version FROM site_settings WHERE setting_key='maintenance' FOR UPDATE");
            $query->execute();
            $row=$query->fetch(\PDO::FETCH_ASSOC);
            if (!$row || (int)$row['version']!==$expectedVersion) throw new HttpException(409,'ADMIN_SETTINGS_CONFLICT');
            $before=json_decode($row['value_json'],true,512,JSON_THROW_ON_ERROR);
            $version=$expectedVersion+1;
            $this->pdo->prepare("UPDATE site_settings SET value_json=?,version=?,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE setting_key='maintenance'")
                ->execute([$enabled ? 'true' : 'false',$version,$actorId]);
            $context=['setting'=>'maintenance','before'=>$before,'after'=>$enabled,'version'=>$version];
            $this->pdo->prepare("INSERT INTO log_entries(type,error_code,user_id,context_json,created_at) VALUES ('admin_audit','MAINTENANCE_CHANGED',?,?,UTC_TIMESTAMP())")
                ->execute([$actorId,json_encode($context,JSON_THROW_ON_ERROR)]);
            $id=(int)$this->pdo->lastInsertId();
            // Enable before commit; disable only after commit. A crash cannot
            // publish public access while the database still requires a stop.
            if ($enabled) $this->signal->publish(true);
            $this->pdo->commit();
            if (!$enabled) $this->signal->publish(false);
            return ['enabled'=>$enabled,'version'=>$version,'audit_id'=>$id,'audit_context'=>$context];
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}
