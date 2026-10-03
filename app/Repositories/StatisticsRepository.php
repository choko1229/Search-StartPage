<?php
declare(strict_types=1);
namespace App\Repositories;
final class StatisticsRepository {
    public function __construct(private readonly \PDO $pdo){}
    public function record(array $events):void {
        $this->pdo->beginTransaction();
        try{
            $query=$this->pdo->prepare('INSERT INTO statistics_events(event_id,anonymous_id,event_type,event_data,source,created_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
            foreach($events as $event)$query->execute([$event['event_id'],$event['anonymous_id'],$event['event_type'],json_encode((object)$event['event_data'],JSON_THROW_ON_ERROR),$event['source'],gmdate('Y-m-d H:i:s',$event['created_at'])]);
            $this->pdo->commit();
        }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}
