<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Services\{ProviderPresets,PresetState};
use App\Http\HttpException;

final class ProviderPresetRepository
{
    public function __construct(private readonly \PDO $pdo){}
    public function read():array
    {
        $query=$this->pdo->prepare("SELECT value_json,version FROM site_settings WHERE setting_key='provider_presets'");$query->execute();$row=$query->fetch(\PDO::FETCH_ASSOC);
        if(!$row)throw new HttpException(503,'ADMIN_SETTINGS_UNAVAILABLE');
        return ['presets'=>ProviderPresets::validate(json_decode($row['value_json'],true,32,JSON_THROW_ON_ERROR)),'version'=>(int)$row['version']];
    }
    public function update(array $presets,int $version,int $actor,PresetState $state):array
    {
        $presets=ProviderPresets::validate($presets);
        return $state->synchronized(function()use($presets,$version,$actor,$state):array{
            $this->pdo->beginTransaction();
            try{
                $query=$this->pdo->prepare("SELECT value_json,version FROM site_settings WHERE setting_key='provider_presets' FOR UPDATE");$query->execute();$row=$query->fetch(\PDO::FETCH_ASSOC);
                if(!$row||(int)$row['version']!==$version)throw new HttpException(409,'ADMIN_SETTINGS_CONFLICT');
                $before=ProviderPresets::validate(json_decode($row['value_json'],true,32,JSON_THROW_ON_ERROR));$state->invalidate();
                $this->pdo->prepare("UPDATE site_settings SET value_json=?,version=version+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE setting_key='provider_presets'")->execute([json_encode($presets,JSON_THROW_ON_ERROR),$actor]);
                $this->pdo->prepare("INSERT INTO log_entries(type,error_code,user_id,context_json,created_at) VALUES ('admin_audit','PROVIDER_PRESETS_CHANGED',?,?,UTC_TIMESTAMP())")->execute([$actor,json_encode(['setting'=>'provider_presets','before'=>$before,'after'=>$presets,'version'=>$version+1],JSON_THROW_ON_ERROR)]);
                $audit=(int)$this->pdo->lastInsertId();$this->pdo->commit();$state->publish($presets);
                return ['presets'=>$presets,'version'=>$version+1,'audit_id'=>$audit];
            }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
        });
    }
}
