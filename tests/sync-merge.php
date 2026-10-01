<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\SyncMerge;use App\Http\HttpException;
$count=0;$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;echo "PASS: $label\n";};
$merge=new SyncMerge();$obj=static fn($text)=>json_decode($text);
$result=$merge->merge($obj('{"settings":{"theme":"light","font":16}}'),$obj('{"settings":{"theme":"dark","font":16}}'),$obj('{"settings":{"font":18,"theme":"light"}}'),(object)[]);
$check($result['document']->settings->theme==='dark' && $result['document']->settings->font===18 && !$result['conflicts'],'independent fields and key order');
$result=$merge->merge($obj('{"settings":{"theme":"light"}}'),$obj('{"settings":{"theme":"dark"}}'),$obj('{"settings":{"theme":"custom"}}'),(object)[]);
$check(count($result['conflicts'])===1 && $result['conflicts'][0]['id']==='["settings","theme"]','same field conflict has JS-compatible path');
$result=$merge->merge($obj('{"favorites":{"a":{"id":"a","name":"Old"}}}'),$obj('{"favorites":{}}'),$obj('{"favorites":{"a":{"id":"a","name":"New"}}}'),(object)[]);
$check(!$result['conflicts'][0]['local']['present'] && $result['conflicts'][0]['cloud']['value']->name==='New','deletion versus edit');
$result=$merge->merge($obj('{"settings":{"a":null}}'),$obj('{"settings":{}}'),$obj('{"settings":{"a":false}}'),(object)['["settings","a"]'=>'cloud']);
$check($result['document']->settings->a===false && !$result['conflicts'],'null missing and false remain distinct');
$result=$merge->merge($obj('{"settings":{"a":[1]}}'),$obj('{"settings":{"a":[2]}}'),$obj('{"settings":{"a":[3]}}'),(object)[]);
$check(count($result['conflicts'])===1,'arrays conflict as one field');
$result=$merge->merge((object)[],$obj('{"settings":{"a":1}}'),$obj('{"settings":{"b":2}}'),(object)[]);
$check($result['document']->settings->a===1 && $result['document']->settings->b===2,'new object fields merge');
$result=$merge->merge($obj('{"settings":{"a":1}}'),$obj('{"settings":{"a":1.0}}'),$obj('{"settings":{"a":2}}'),(object)[]);
$check($result['document']->settings->a===2 && !$result['conflicts'],'JSON numeric equality matches JS');
try {$merge->merge((object)[],(object)[],(object)[],(object)['x'=>'invalid']);throw new RuntimeException('bad choice accepted');}catch(HttpException $error){$check($error->status===422,'invalid resolution choice rejected');}
echo "$count PHP merge assertions passed.\n";
