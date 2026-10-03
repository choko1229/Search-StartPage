<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Http\HttpException;

final class AdminRoleRepository
{
    public function __construct(private readonly \PDO $pdo){}
    public function version():int
    {
        $query=$this->pdo->prepare("SELECT version FROM site_settings WHERE setting_key='admin_roles'");$query->execute();$version=$query->fetchColumn();
        if($version===false)throw new HttpException(503,'ADMIN_SETTINGS_UNAVAILABLE');return (int)$version;
    }
    public function update(int $target,bool $enabled,bool $expected,int $version,int $actor):array
    {
        $this->pdo->beginTransaction();
        try {
            // All role edits serialize here, including edits to different accounts.
            $query=$this->pdo->prepare("SELECT version FROM site_settings WHERE setting_key='admin_roles' FOR UPDATE");$query->execute();$current=$query->fetchColumn();
            if($current===false)throw new HttpException(503,'ADMIN_SETTINGS_UNAVAILABLE');
            // Use the same users-before-membership order as initial OAuth claims.
            $query=$this->pdo->prepare('SELECT id FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE');$query->execute([$actor,$target]);$users=array_map('intval',$query->fetchAll(\PDO::FETCH_COLUMN));
            $query=$this->pdo->prepare('SELECT user_id FROM administrators WHERE admin_flag=1 ORDER BY user_id FOR UPDATE');$query->execute();$administrators=array_map('intval',$query->fetchAll(\PDO::FETCH_COLUMN));
            // Middleware ran before the wait: recheck current authority inside the lock.
            if(!in_array($actor,$administrators,true))throw new HttpException(403,'ADMIN_REQUIRED');
            if(!in_array($target,$users,true))throw new HttpException(404,'USER_NOT_FOUND');
            $before=in_array($target,$administrators,true);
            if((int)$current!==$version||$before!==$expected)throw new HttpException(409,'ADMIN_SETTINGS_CONFLICT');
            if($before&&!$enabled&&count($administrators)<=1)throw new HttpException(409,'LAST_ADMIN_REQUIRED');
            if($before===$enabled){$this->pdo->commit();return ['user_id'=>$target,'admin_flag'=>$enabled,'version'=>$version,'changed'=>false];}
            $this->pdo->prepare('INSERT INTO administrators(user_id,admin_flag,created_by,created_at,updated_at) VALUES (?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE admin_flag=VALUES(admin_flag),updated_at=UTC_TIMESTAMP()')->execute([$target,$enabled?1:0,$actor]);
            $this->pdo->prepare("UPDATE site_settings SET version=version+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE setting_key='admin_roles'")->execute([$actor]);
            $context=['target_user_id'=>$target,'before'=>$before,'after'=>$enabled,'version'=>$version+1];
            $this->pdo->prepare("INSERT INTO log_entries(type,error_code,user_id,context_json,created_at) VALUES ('admin_audit','ADMIN_ROLE_CHANGED',?,?,UTC_TIMESTAMP())")->execute([$actor,json_encode($context,JSON_THROW_ON_ERROR)]);
            $audit=(int)$this->pdo->lastInsertId();$this->pdo->commit();
            return ['user_id'=>$target,'admin_flag'=>$enabled,'version'=>$version+1,'changed'=>true,'audit_id'=>$audit];
        }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}
