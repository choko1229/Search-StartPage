<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1') exit(1);
require dirname(__DIR__) . '/app/autoload.php';
use App\Services\{WeatherService, WeatherCache};
use App\Controllers\WeatherController;
use App\Http\{HttpException, Request};
$passed = 0;
$check = static function(bool $condition) use (&$passed): void { if (!$condition) throw new RuntimeException('Weather assertion failed'); $passed++; };
$reject = static function(callable $fn, string $code = 'WEATHER_UNAVAILABLE') use ($check): void {
    try { $fn(); throw new RuntimeException('Rejected input accepted'); }
    catch (HttpException $e) { $check($e->errorCode === $code); }
};
$now = 1700000000; $calls = 0; $lastUrl = '';
$payload = ['current'=>['weather_code'=>0,'temperature_2m'=>0,'time'=>$now], 'current_units'=>['temperature_2m'=>'°C']];
$transport = static function(string $url) use (&$calls, &$lastUrl, &$payload): string { $calls++; $lastUrl=$url; return json_encode($payload, JSON_THROW_ON_ERROR); };
$service = new WeatherService([], $transport);
$region = ['latitude'=>35.6849, 'longitude'=>139.7649];
$check(WeatherService::region($region) === ['latitude'=>35.68, 'longitude'=>139.76]);
$check(WeatherService::region(['latitude'=>0,'longitude'=>0]) === ['latitude'=>0.0,'longitude'=>0.0]);
foreach ([[],['latitude'=>'35','longitude'=>0],['latitude'=>null,'longitude'=>0],['latitude'=>91,'longitude'=>0],['latitude'=>0,'longitude'=>181],['latitude'=>NAN,'longitude'=>0],['latitude'=>0,'longitude'=>INF],$region+['url'=>'https://example.com']] as $bad) $reject(fn()=>WeatherService::region($bad), 'INVALID_INPUT');
foreach (['clear'=>[0,1],'cloudy'=>[2,3],'fog'=>[45,48],'rain'=>[51,53,55,56,57,61,63,65,66,67,80,81,82],'snow'=>[71,73,75,77,85,86],'storm'=>[95,96,97,99]] as $type=>$codes) {
    foreach ($codes as $code) { $payload['current']['weather_code']=$code; $result=$service->current($region,$now); $check($result['weather']===$type && $result['temperature']===0 && $result['expiresAt']===$now+900); }
}
$check(str_starts_with($lastUrl, 'https://api.open-meteo.com/v1/forecast?') && str_contains($lastUrl, 'latitude=35.68') && !str_contains($lastUrl, 'apikey'));
$baseline = $payload;
foreach ([['weather_code'=>4],['weather_code'=>'0'],['temperature_2m'=>null],['temperature_2m'=>'12'],['temperature_2m'=>71],['time'=>$now-7201],['time'=>$now+901]] as $bad) { $payload=$baseline; $payload['current']=array_replace($payload['current'],$bad); $reject(fn()=>$service->current($region,$now)); }
$payload=$baseline; $payload['current_units']['temperature_2m']='°F'; $reject(fn()=>$service->current($region,$now));
foreach (['not json', 'null', '[]', str_repeat('x',32769)] as $body) $reject(fn()=>(new WeatherService([],static fn()=>$body))->current($region,$now));
$reject(fn()=>(new WeatherService([],static function(){throw new RuntimeException('upstream secret');}))->current($region,$now));
foreach ([['enabled'=>false],['mode'=>'custom'],['mode'=>'customer'],['mode'=>'customer','api_key'=>[]]] as $config) $reject(fn()=>(new WeatherService($config,$transport))->current($region,$now));
$payload=$baseline; $customer=new WeatherService(['mode'=>'customer','api_key'=>'fixture-key'], $transport);
$check($customer->current($region,$now)['weather']==='storm' && str_starts_with($lastUrl,'https://customer-api.open-meteo.com/') && str_contains($lastUrl,'apikey=fixture-key'));
$directory = sys_get_temp_dir().'/search-weather-'.bin2hex(random_bytes(6));
try {
    $cache=new WeatherCache($directory); $before=$calls;
    $first=$cache->read($region,$service,$now); $check($calls===$before+1);
    $check($cache->read($region,$service,$now+1)===$first && $calls===$before+1);
    $check($cache->read(['latitude'=>35.681,'longitude'=>139.761],$service,$now+2)===$first && $calls===$before+1);
    $payload['current']['time']=$now+900; $cache->read($region,$service,$now+900); $check($calls===$before+2);
    $reject(fn()=>$cache->read($region,new WeatherService(['enabled'=>false],$transport),$now+901));
    $controller=new WeatherController($service,$cache);
    $payload['current']['time']=time();
    $response=$controller->current(new Request('POST','/api/weather',body:$region));
    $data=json_decode($response->body,true,12,JSON_THROW_ON_ERROR);
    $check($response->status===200 && $data['success']===true && $data['data']['weather']==='storm');
    $check(!str_contains($response->body,'latitude') && !str_contains($response->body,'fixture-key'));
    $reject(fn()=>$controller->current(new Request('POST','/api/weather',body:$region+['url'=>'http://127.0.0.1'])), 'INVALID_INPUT');
    foreach(glob($directory.'/*.json')?:[] as $file) file_put_contents($file,'corrupt');
    $before=$calls; $cache->read($region,$service,time()); $check($calls===$before+1);
    foreach(glob($directory.'/*.json')?:[] as $file) $check(!str_contains(file_get_contents($file),'latitude') && !str_contains(file_get_contents($file),'fixture-key'));
} finally { foreach(glob($directory.'/*.json')?:[] as $file) unlink($file); if(is_dir($directory))rmdir($directory); }
$directory = sys_get_temp_dir().'/search-weather-failure-'.bin2hex(random_bytes(6));
try {
    $failureCalls=0;
    $failing=new WeatherService([],static function()use(&$failureCalls){$failureCalls++;throw new RuntimeException('network');});
    $cache=new WeatherCache($directory);
    $reject(fn()=>$cache->read($region,$failing,$now)); $reject(fn()=>$cache->read($region,$failing,$now+59)); $check($failureCalls===1);
    $reject(fn()=>$cache->read($region,$failing,$now+60)); $check($failureCalls===2);
} finally { foreach(glob($directory.'/*.json')?:[] as $file) unlink($file); if(is_dir($directory))rmdir($directory); }
echo "$passed weather assertions passed.\n";
