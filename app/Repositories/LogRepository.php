<?php
declare(strict_types=1);
namespace App\Repositories;

final class LogRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function recordApplication(array $entry): void
    {
        $query=$this->pdo->prepare('INSERT INTO log_entries(event_id,type,error_code,user_id,context_json,created_at,file_written) VALUES (?,?,?,(SELECT id FROM users WHERE id=?),?,?,?) ON DUPLICATE KEY UPDATE file_written=GREATEST(file_written,VALUES(file_written))');
        $query->execute([$entry['event_id'],$entry['type'],$entry['error_code'],$entry['user_id'],json_encode($entry['context'],JSON_THROW_ON_ERROR),$entry['created_at'],$entry['file_written'] ? 1 : 0]);
    }

    public function listing(array $filters): array
    {
        $conditions=['created_at>=UTC_TIMESTAMP()-INTERVAL 90 DAY'];
        $parameters=[];
        foreach (['type','user_id','error_code'] as $key) {
            if ($filters[$key]==='') continue;
            $conditions[]=$key.'=?'; $parameters[]=$filters[$key];
        }
        if ($filters['from']!=='') { $conditions[]='created_at>=?'; $parameters[]=$filters['from'].' 00:00:00'; }
        if ($filters['to']!=='') { $conditions[]='created_at<=?'; $parameters[]=$filters['to'].' 23:59:59'; }
        if ($filters['q']!=='') { $conditions[]='(LOCATE(?,error_code)>0 OR LOCATE(?,context_json)>0)'; array_push($parameters,$filters['q'],$filters['q']); }
        $where='WHERE '.implode(' AND ',$conditions);
        $count=$this->pdo->prepare('SELECT COUNT(*) FROM log_entries '.$where); $count->execute($parameters);
        $total=(int)$count->fetchColumn();
        $query=$this->pdo->prepare('SELECT id,type,error_code,user_id,context_json,created_at,file_written FROM log_entries '.$where.' ORDER BY created_at DESC,id DESC LIMIT ? OFFSET ?');
        foreach ($parameters as $index=>$value) $query->bindValue($index+1,$value,\PDO::PARAM_STR);
        $query->bindValue(count($parameters)+1,$filters['page_size'],\PDO::PARAM_INT);
        $query->bindValue(count($parameters)+2,($filters['page']-1)*$filters['page_size'],\PDO::PARAM_INT);
        $query->execute(); $items=$query->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            $item['id']=(int)$item['id'];
            $item['user_id']=$item['user_id']===null ? null : (int)$item['user_id'];
            $item['file_written']=(int)$item['file_written']===1;
            $item['context']=json_decode($item['context_json'],true,512,JSON_THROW_ON_ERROR);
            unset($item['context_json']);
        }
        return ['items'=>$items,'total'=>$total,'filters'=>$filters,'page'=>$filters['page'],'page_size'=>$filters['page_size']];
    }

    public function purgeExpired(?int $now=null): int
    {
        $cutoff=gmdate('Y-m-d H:i:s',($now ?? time())-90*86400);
        $query=$this->pdo->prepare('DELETE FROM log_entries WHERE created_at<?');
        $query->execute([$cutoff]);
        return $query->rowCount();
    }
}
