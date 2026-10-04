<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Execute a trusted release's stopped entry points in its isolated, disposable stage. */
final class UpdateCompatibility
{
    private function remove(string $path): void
    {
        UpdatePackagePaths::directory($path);
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $file){if($file->isLink())throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');if(!($file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname())))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
        if(!rmdir($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
    public function validate(string $stage,array $manifest): int
    {
        (new UpdateStage())->validate($stage,$manifest);$stage=realpath($stage);
        foreach(['app/Services/UpdateAccess.php','app/Services/UpdateJournal.php','app/Services/UpdateCommands.php','app/Services/UpdateRunner.php','app/Services/UpdateDatabaseMerge.php','app/Repositories/UpdateHistoryRepository.php','bin/run-update.php','bin/update-task.php','bin/log-maintenance.php','bin/update-check-worker.php'] as $path)if(!isset($manifest['files'][$path]))throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');
        $storage=$stage.'/storage';$config=$stage.'/config/config.php';
        if(file_exists($storage)||is_link($storage)||file_exists($config)||is_link($config))throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');
        if(!mkdir($storage,0700))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        $createdConfig=false;$count=0;
        try{
            if(!mkdir($storage.'/updates',0700)||!mkdir($storage.'/updates/access',0700))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $marker=$storage.'/config-read';$stream=@fopen($config,'xb');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$createdConfig=true;
            try{$body='<?php file_put_contents('.var_export($marker,true).',"read");throw new RuntimeException("UPDATE_PROBE_CONFIG_READ");';if(fwrite($stream,$body)!==strlen($body)||!chmod($config,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}finally{fclose($stream);}
            if(file_put_contents($storage.'/updates/access/pending','update')!==6||!chmod($storage.'/updates/access/pending',0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $process=new UpdateProcess();$wrapper=$storage.'/probe.php';
            $http=[['GET','/','ja'],['GET','/account','en'],['GET','/api/admin/update','en'],['POST','/api/sync','ja'],['POST','/installer','ja'],['HEAD','/api/sync','en']];
            foreach($http as [$method,$path,$locale]){
                $server=['REQUEST_METHOD'=>$method,'REQUEST_URI'=>$path,'HTTP_ACCEPT_LANGUAGE'=>$locale,'CONTENT_TYPE'=>'application/json','HTTP_HOST'=>'localhost','REMOTE_ADDR'=>'127.0.0.1','SERVER_PORT'=>'80'];
                $source='<?php $_GET=[];$_POST=[];$_COOKIE=[];$_SERVER='.var_export($server,true).';ob_start();register_shutdown_function(function(){ $body=ob_get_clean();$json=json_decode($body,true);echo json_encode(["status"=>http_response_code(),"code"=>$json["error"]["code"]??null,"body"=>$body]);});require '.var_export($stage.'/public/index.php',true).';';
                if(file_put_contents($wrapper,$source)!==strlen($source)||!chmod($wrapper,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
                try{$result=json_decode($process->script($wrapper,[],10),true,8,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');}
                $api=str_starts_with($path,'/api/');$valid=$method==='HEAD'?($result['body']??null)==='':($api?($result['code']??null)==='UPDATE_IN_PROGRESS':str_contains($result['body']??'',$locale==='ja'?'更新作業中':'An update is in progress'));
                if(($result['status']??null)!==503||!$valid||is_file($marker)||is_dir($storage.'/logs')||is_dir($storage.'/log-pending'))throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');++$count;
            }
            foreach(['bin/log-maintenance.php','bin/update-check-worker.php'] as $path){
                $source='<?php putenv("SEARCH_TEST_MODE=1");$argv=['.var_export($stage.'/'.$path,true).',"--cycles=1"];require '.var_export($stage.'/'.$path,true).';';
                if(file_put_contents($wrapper,$source)!==strlen($source)||!chmod($wrapper,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
                try{$result=json_decode(trim($process->script($wrapper,[],10)),true,8,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');}
                if(($result['status']??null)!=='paused'||is_file($marker)||is_dir($storage.'/logs')||is_dir($storage.'/log-pending'))throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');++$count;
            }
            // Actual candidate gate must implement the inherited descriptor protocol as well.
            $source='<?php require '.var_export($stage.'/app/Services/UpdateAccess.php',true).';unlink('.var_export($storage.'/updates/access/pending',true).');$gate=new App\\Services\\UpdateAccess('.var_export($storage.'/updates/access',true).');$gate->exclusive(function()use($gate){echo json_encode(["protocol"=>App\\Services\\UpdateAccess::PROTOCOL,"descriptors"=>array_keys($gate->childDescriptors()),"stopped"=>$gate->enter()===null]);});';
            if(file_put_contents($wrapper,$source)!==strlen($source)||!chmod($wrapper,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            try{$result=json_decode($process->script($wrapper,[],10),true,8,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');}
            if($result!==['protocol'=>1,'descriptors'=>[3,4],'stopped'=>true]||is_file($marker))throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');++$count;
            // The newly installed code must retain request identity in our durable journal.
            $requestId=str_repeat('a',32);
            $source='<?php require '.var_export($stage.'/app/autoload.php',true).';$directory='.var_export($storage.'/updates/journal',true).';$journal=new App\\Services\\UpdateJournal($directory);$state=$journal->start("1.0.0","2.0.0",0,requestId:'.var_export($requestId,true).');$saved=(new App\\Services\\UpdateJournal($directory))->status();echo json_encode(["format"=>$saved["format"],"request_id"=>$saved["job"]["request_id"]??null,"same"=>$state===$saved]);';
            if(file_put_contents($wrapper,$source)!==strlen($source)||!chmod($wrapper,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            try{$result=json_decode($process->script($wrapper,[],10),true,8,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');}
            if($result!==['format'=>3,'request_id'=>$requestId,'same'=>true]||is_file($marker))throw new HttpException(422,'UPDATE_GATE_INCOMPATIBLE');++$count;
        }finally{
            if($createdConfig&&!unlink($config))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $this->remove($storage);
        }
        (new UpdateStage())->validate($stage,$manifest);
        return $count;
    }
}
