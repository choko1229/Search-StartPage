<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if(PHP_SAPI!=='cli')exit(1);
$events=[];$delays=[];$calls=0;
$exit=(new App\Services\LogSchedule())->run(function()use(&$calls):array{if(++$calls===1)throw new RuntimeException('generated-secret-marker');return ['database_rows'=>1];},function(array $event)use(&$events):void{$events[]=$event;},86400,3600,3,function(int $delay)use(&$delays):void{$delays[]=$delay;});
if($exit!==0||$calls!==3||$delays!==[3600,86400]||array_column($events,'status')!==['failed','success','success'])throw new RuntimeException('Schedule retry timing failed');
if(str_contains(json_encode($events),'generated-secret-marker')||isset($events[0]['counts']))throw new RuntimeException('Schedule error exposed details');
foreach([[0,1,1],[86401,1,1],[60,61,1],[60,1,-1]] as $arguments){try{(new App\Services\LogSchedule())->run(fn()=>[],fn()=>null,...$arguments);throw new LogicException('Invalid schedule accepted');}catch(InvalidArgumentException){}}
if((new App\Services\LogSchedule())->run(fn()=>throw new RuntimeException('failure'),fn()=>null,1,1,1)!==1)throw new RuntimeException('Failure exit code lost');
echo "Log schedule: normal interval, failure retry/recovery, finite cycles, validation and safe error reporting passed.\n";
