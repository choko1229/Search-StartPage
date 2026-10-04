<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

final class UpdateRuntime
{
    public function __construct(private readonly string $root) {}
    public function run(string $task,string $version,UpdateAccess $access): array
    {
        if(!in_array($task,['migrate','health'],true))throw new HttpException(422,'INVALID_UPDATE_PROCESS');
        UpdateManifest::version($version);UpdatePackagePaths::directory($this->root);
        $output=(new UpdateProcess())->guardedScript($this->root.'/bin/update-task.php',[$task,$version],$access);
        try{
            $data=json_decode($output,true,8,JSON_THROW_ON_ERROR);$keys=$task==='migrate'?['format','protocol','task','version','pid','applied']:['format','protocol','task','version','pid','migrations','html_bytes'];
            if(!is_array($data))throw new \RuntimeException();$actual=array_keys($data);sort($actual);sort($keys);
            if($actual!==$keys||$data['format']!==1||$data['protocol']!==1||$data['task']!==$task||$data['version']!==$version||!is_int($data['pid'])||$data['pid']<1||$data['pid']===getmypid())throw new \RuntimeException();
            if($task==='migrate'){
                if(!is_int($data['applied'])||$data['applied']<0||$data['applied']>4096)throw new \RuntimeException();
            }else{
                if(!is_int($data['migrations'])||$data['migrations']<1||$data['migrations']>4096||!is_array($data['html_bytes']))throw new \RuntimeException();
                $locales=array_keys($data['html_bytes']);sort($locales);if($locales!==['en','ja'])throw new \RuntimeException();
                foreach($data['html_bytes'] as $size)if(!is_int($size)||$size<1||$size>16777216)throw new \RuntimeException();
            }
            return $data;
        }catch(\Throwable){throw new HttpException(422,'UPDATE_TASK_INVALID_RESPONSE');}
    }
}
