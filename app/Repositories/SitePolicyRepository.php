<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Services\{SitePolicy,PolicyState};
use App\Http\HttpException;

final class SitePolicyRepository
{
    public function __construct(private readonly \PDO $pdo) {}
    public function read(bool $shareLock=false): array
    {
        $query=$this->pdo->prepare("SELECT value_json,version FROM site_settings WHERE setting_key='site_policy'".($shareLock ? ' LOCK IN SHARE MODE' : ''));
        $query->execute();$row=$query->fetch(\PDO::FETCH_ASSOC);
        if(!$row)throw new HttpException(503,'ADMIN_SETTINGS_UNAVAILABLE');
        return ['policy'=>SitePolicy::validate(json_decode($row['value_json'],true,32,JSON_THROW_ON_ERROR)),'version'=>(int)$row['version']];
    }
    public function update(array $policy,int $version,int $actor,PolicyState $state): array
    {
        $policy=SitePolicy::validate($policy);
        return $state->synchronized(function()use($policy,$version,$actor,$state):array {
            $this->pdo->beginTransaction();
            try {
                $query=$this->pdo->prepare("SELECT value_json,version FROM site_settings WHERE setting_key='site_policy' FOR UPDATE");$query->execute();$row=$query->fetch(\PDO::FETCH_ASSOC);
                if(!$row||(int)$row['version']!==$version)throw new HttpException(409,'ADMIN_SETTINGS_CONFLICT');
                $before=SitePolicy::validate(json_decode($row['value_json'],true,32,JSON_THROW_ON_ERROR));
                $state->invalidate();
                $this->pdo->prepare("UPDATE site_settings SET value_json=?,version=version+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE setting_key='site_policy'")
                    ->execute([json_encode($policy,JSON_THROW_ON_ERROR),$actor]);
                $context=['setting'=>'site_policy','before'=>$before,'after'=>$policy,'version'=>$version+1];
                $this->pdo->prepare("INSERT INTO log_entries(type,error_code,user_id,context_json,created_at) VALUES ('admin_audit','SITE_POLICY_CHANGED',?,?,UTC_TIMESTAMP())")
                    ->execute([$actor,json_encode($context,JSON_THROW_ON_ERROR)]);
                $audit=(int)$this->pdo->lastInsertId();$this->pdo->commit();
                $state->publish($policy);
                return ['policy'=>$policy,'version'=>$version+1,'audit_id'=>$audit];
            }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
        });
    }
}
