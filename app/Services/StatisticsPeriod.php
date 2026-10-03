<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

final class StatisticsPeriod
{
    public static function parse(array $query, ?\DateTimeImmutable $now=null): array
    {
        $today=($now ?? new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->setTime(0,0);
        $end=$query['end'] ?? $today->format('Y-m-d');
        $start=$query['start'] ?? $today->modify('-29 days')->format('Y-m-d');
        $dates=[];
        foreach([$start,$end] as $value){
            if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))throw new HttpException(422,'INVALID_INPUT');
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('UTC'));
            if(!$date||$date->format('Y-m-d')!==$value||$date>$today)throw new HttpException(422,'INVALID_INPUT');
            $dates[]=$date;
        }
        [$from,$to]=$dates;
        if($from>$to||$from->diff($to)->days>365)throw new HttpException(422,'INVALID_INPUT');
        return ['start'=>$start,'end'=>$end,'from'=>$from->format('Y-m-d H:i:s'),'until'=>$to->modify('+1 day')->format('Y-m-d H:i:s')];
    }
}
