<?php
declare(strict_types=1);
namespace App\Services;

final class LogSchedule
{
    public function run(callable $cleanup,callable $report,int $interval=86400,int $retry=3600,int $cycles=0,?callable $wait=null): int
    {
        if($interval<1||$interval>86400||$retry<1||$retry>$interval||$cycles<0)throw new \InvalidArgumentException('Invalid log schedule');
        $wait??=static fn(int $seconds)=>sleep($seconds);
        $last=0;
        for($cycle=0;$cycles===0||$cycle<$cycles;$cycle++){
            try {$counts=$cleanup();$last=0;$report(['at'=>gmdate(DATE_ATOM),'status'=>'success','counts'=>$counts,'next_run_in'=>$interval]);}
            catch(\Throwable){$last=1;$report(['at'=>gmdate(DATE_ATOM),'status'=>'failed','next_run_in'=>$retry]);}
            if($cycles===0||$cycle+1<$cycles)$wait($last===0?$interval:$retry);
        }
        return $last;
    }
}
