<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\SyncRepository;
use App\Http\HttpException;
final class SyncRetention
{
    private static function bounded(mixed $value,int $default,int $max): int {return is_int($value) && $value>=1 && $value<=$max?$value:$default;}
    public static function prune(object $document,?int $now=null): object
    {
        if(!isset($document->history))return $document;
        $limit=self::bounded($document->settings->historyLimit??null,300,10000);
        $days=self::bounded($document->settings->historyDays??null,90,3650);
        $cutoff=($now??(int)floor(microtime(true)*1000))-$days*86400000;
        $rows=array_filter((array)$document->history,static fn($row)=>$row->at>$cutoff);
        uasort($rows,static fn($a,$b)=>($b->at<=>$a->at)?:strcmp($a->id,$b->id));
        $result=clone $document;$result->history=(object)array_slice($rows,0,$limit,true);return $result;
    }
    public static function read(SyncRepository $repository,int $user): array
    {
        for($attempt=0;$attempt<3;$attempt++) {
            $current=$repository->read($user);$document=self::prune($current['document']);
            if($document==$current['document'])return $current;
            $saved=$repository->write($user,$current['version'],$document);
            if($saved!==null)return $saved;
        }
        throw new HttpException(409,'SYNC_CONFLICT');
    }
}
