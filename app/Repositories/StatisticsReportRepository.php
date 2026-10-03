<?php
declare(strict_types=1);
namespace App\Repositories;

final class StatisticsReportRepository
{
    public function __construct(private readonly \PDO $pdo){}
    private function rows(string $sql,array $parameters=[]): array
    {
        $query=$this->pdo->prepare($sql);$query->execute($parameters);return $query->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function report(array $period): array
    {
        $range=[$period['from'],$period['until']];
        // A single consistent read keeps all totals aligned while new events arrive.
        $this->pdo->beginTransaction();
        try {
            $accounts=$this->rows('SELECT (SELECT COUNT(*) FROM users) AS users,
                (SELECT COUNT(DISTINCT d.user_id) FROM devices d JOIN login_tokens t ON t.device_id=d.id WHERE t.expires_at>UTC_TIMESTAMP()) AS logged_in_users,
                (SELECT COALESCE(SUM(file_size),0) FROM backgrounds WHERE deleted=0) AS storage_bytes,
                (SELECT COUNT(*) FROM users WHERE created_at>=? AND created_at<?) AS new_users',$range)[0];
            $summary=array_map('intval',$accounts);
            foreach(['search'=>'searches','ai_search'=>'ai_searches','favorite_open'=>'favorite_opens'] as $type=>$key)$summary[$key]=0;
            foreach($this->rows('SELECT event_type,COUNT(*) AS total FROM statistics_events WHERE created_at>=? AND created_at<? GROUP BY event_type',$range) as $row){
                $key=['search'=>'searches','ai_search'=>'ai_searches','favorite_open'=>'favorite_opens'][$row['event_type']]??null;
                if($key)$summary[$key]=(int)$row['total'];
            }
            $end=new \DateTimeImmutable($period['end'],new \DateTimeZone('UTC'));
            foreach(['dau'=>1,'wau'=>7,'mau'=>30] as $key=>$days){
                $summary[$key]=(int)$this->rows('SELECT COUNT(DISTINCT anonymous_id) AS total FROM statistics_events WHERE created_at>=? AND created_at<?',[$end->modify('-'.($days-1).' days')->format('Y-m-d H:i:s'),$period['until']])[0]['total'];
            }
            $first='SELECT anonymous_id,MIN(DATE(created_at)) AS first_day FROM statistics_events GROUP BY anonymous_id';
            $summary['new_anonymous_devices']=(int)$this->rows("SELECT COUNT(*) AS total FROM ($first) f WHERE first_day>=? AND first_day<?",$range)[0]['total'];
            // D7 measures activity on the seventh UTC day after first activity.
            // Only cohorts whose complete seventh day has elapsed are eligible.
            $retention=$this->rows("SELECT COUNT(*) AS eligible,COALESCE(SUM(EXISTS(SELECT 1 FROM statistics_events e WHERE e.anonymous_id=f.anonymous_id AND e.created_at>=DATE_ADD(f.first_day,INTERVAL 7 DAY) AND e.created_at<DATE_ADD(f.first_day,INTERVAL 8 DAY))),0) AS returned_devices FROM ($first) f WHERE first_day>=? AND first_day<? AND DATE_ADD(first_day,INTERVAL 8 DAY)<=LEAST(?,UTC_DATE())",[...$range,$period['until']])[0];
            $retention=array_map('intval',$retention);$retention['returning']=$retention['returned_devices'];unset($retention['returned_devices']);$retention['percent']=$retention['eligible']>0?round(100*$retention['returning']/$retention['eligible'],2):null;
            $breakdowns=[];
            foreach(['search_engines'=>['search','provider'],'ai_providers'=>['ai_search','provider'],'features'=>['feature','feature']] as $key=>[$type,$field]){
                // Field and type come exclusively from this fixed map.
                $rows=$this->rows("SELECT JSON_UNQUOTE(JSON_EXTRACT(event_data,'$.$field')) AS label,COUNT(*) AS total FROM statistics_events WHERE event_type=? AND created_at>=? AND created_at<? GROUP BY label ORDER BY total DESC,label ASC",[$type,...$range]);
                $breakdowns[$key]=array_map(static fn($row)=>['label'=>$row['label'],'total'=>(int)$row['total']],$rows);
            }
            $sources=$this->rows('SELECT source AS label,COUNT(*) AS total FROM statistics_events WHERE created_at>=? AND created_at<? GROUP BY source ORDER BY source',$range);
            $total=array_sum(array_column($sources,'total'));
            $breakdowns['sources']=array_map(static fn($row)=>['label'=>$row['label'],'total'=>(int)$row['total'],'percent'=>$total>0?round(100*(int)$row['total']/$total,2):0.0],$sources);
            $dailyRows=$this->rows("SELECT DATE(created_at) AS day,COUNT(DISTINCT anonymous_id) AS dau,SUM(event_type='search') AS searches,SUM(event_type='ai_search') AS ai_searches,SUM(event_type='favorite_open') AS favorite_opens FROM statistics_events WHERE created_at>=? AND created_at<? GROUP BY day ORDER BY day",$range);
            $byDay=array_column($dailyRows,null,'day');$daily=[];
            for($day=new \DateTimeImmutable($period['start'],new \DateTimeZone('UTC'));$day<=$end;$day=$day->modify('+1 day')){
                $date=$day->format('Y-m-d');$row=$byDay[$date]??[];$item=['day'=>$date];
                foreach(['dau','searches','ai_searches','favorite_opens'] as $key)$item[$key]=(int)($row[$key]??0);
                $daily[]=$item;
            }
            $this->pdo->commit();
            return ['period'=>['start'=>$period['start'],'end'=>$period['end'],'timezone'=>'UTC'],'summary'=>$summary,'retention_d7'=>$retention,'breakdowns'=>$breakdowns,'daily'=>$daily];
        }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}
