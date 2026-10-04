<?php
declare(strict_types=1);
namespace App\Services;

/** DB acts as a durable outbox. A failed file append is retried on the next visit. */
final class AdminAuditLogger
{
    public function __construct(private readonly \PDO $pdo,private readonly FileLogger $files) {}

    public function flush(): void
    {
        $query=$this->pdo->prepare("SELECT id,event_id,type,error_code,user_id,context_json,created_at FROM log_entries WHERE file_written=0 AND (type='admin_audit' OR (type='update_error' AND JSON_UNQUOTE(JSON_EXTRACT(context_json,'$.outbox'))='update_job')) ORDER BY id LIMIT 100");
        $query->execute();
        foreach ($query->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            try {
                $context=['audit_id'=>(int)$row['id'],'user_id'=>$row['user_id']===null ? null : (int)$row['user_id'],'change'=>json_decode($row['context_json'],true,512,JSON_THROW_ON_ERROR)];
                if($row['type']==='update_error')$context['event_id']=$row['event_id'];
                $this->files->write($row['type'],$row['error_code'],$context,strtotime($row['created_at'].' UTC'));
                $this->pdo->prepare('UPDATE log_entries SET file_written=1 WHERE id=?')->execute([$row['id']]);
            } catch (\Throwable) {
                error_log('Admin audit file append pending; persisted in database.');
                break;
            }
        }
    }
}
