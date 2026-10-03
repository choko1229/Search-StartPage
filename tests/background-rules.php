<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\BackgroundInput;use App\Http\HttpException;
$valid=[['type'=>'time','start'=>'23:00','end'=>'06:00'],['type'=>'day','days'=>[0,6]],['type'=>'date','date'=>'2024-02-29'],['type'=>'period','start'=>'2026-01-01','end'=>'2026-12-31'],['type'=>'weather','values'=>['clear','cloudy','fog','rain','snow','storm']],['type'=>'temperature','min'=>-10.5,'max'=>30],['type'=>'season','value'=>'autumn'],['type'=>'random','chance'=>.5],['type'=>'login','value'=>false],['type'=>'device','value'=>'tablet'],['type'=>'screen','minWidth'=>320,'maxWidth'=>1920,'minHeight'=>0,'maxHeight'=>1080]];
$validate=static fn(array $rule)=>BackgroundInput::validate(json_decode(json_encode(['id'=>'rule-test','name'=>'Rules','type'=>'solid','rule'=>$rule],JSON_THROW_ON_ERROR),false,24,JSON_THROW_ON_ERROR));
$passed=0;
foreach($valid as $rule){$item=$validate($rule);if($item['settings']['rule']->type!==$rule['type'])throw new RuntimeException('Condition dropped');$passed++;}
$nested=['operator'=>'and','conditions'=>[$valid[0],['operator'=>'or','conditions'=>[$valid[1],$valid[4]]]]];
$item=$validate($nested);if(count($item['settings']['rule']->conditions[1]->conditions)!==2)throw new RuntimeException('Nested conditions dropped');$passed++;
foreach([['type'=>'date','date'=>'2026-02-30'],['type'=>'period','start'=>'2026-12-01','end'=>'2026-01-01'],['type'=>'day','days'=>[]],['type'=>'weather','values'=>['sunny']],['type'=>'temperature','min'=>20,'max'=>10],['type'=>'random','chance'=>1.1],['type'=>'login','value'=>'true'],['type'=>'screen','minWidth'=>1920,'maxWidth'=>320],['operator'=>'or','conditions'=>[]],['operator'=>'and','conditions'=>array_fill(0,100,$valid[0])]] as $rule){
    try{$validate($rule);throw new RuntimeException('Invalid condition accepted');}catch(HttpException $error){if($error->errorCode!=='INVALID_BACKGROUND')throw $error;$passed++;}
}
echo "$passed background rule validation assertions passed.\n";
