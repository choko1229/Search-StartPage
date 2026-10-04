<?php
declare(strict_types=1);
namespace App\Services;

use App\Config;
use App\Http\HttpException;

/** Private metadata only. This service never downloads or installs a release. */
final class UpdateChecks
{
    private readonly string $repository;
    private readonly string $channel;
    private readonly string $tag;
    private readonly string $identity;
    private readonly GitHubReleases $source;
    public function __construct(private readonly string $directory,Config $config,private readonly string $current,
        ?\Closure $transport=null,private readonly ?\Closure $clock=null,private readonly ?\Closure $audit=null)
    {
        $repo=$config->get('updates.repository','choko1229/Search-StartPage');
        $channel=$config->get('updates.channel','stable');$tag=$config->get('updates.custom_tag','');$token=$config->get('updates.token','');
        if(!is_string($repo)||!is_string($channel)||!is_string($tag)||!is_string($token)||!preg_match('/^[A-Za-z0-9][A-Za-z0-9.+_-]{0,127}$/D',$current))throw new HttpException(422,'INVALID_UPDATE_CONFIGURATION');
        ReleaseCatalog::channel($channel,$tag);
        $this->repository=ReleaseCatalog::repository($repo);$this->channel=$channel;$this->tag=$channel==='custom'?$tag:'';
        $this->source=new GitHubReleases($repo,$transport,$token);
        $this->identity=hash('sha256',json_encode([$repo,$channel,$tag,$token,$current],JSON_THROW_ON_ERROR));
    }
    public function status(): array {return LogFileLock::run($this->directory,fn():array=>$this->read());}
    public function check(?string $channel=null,string $tag='',?int $revision=null,bool $onlyIfDue=false): array
    {
        $useSaved=$channel===null;
        if($channel!==null)ReleaseCatalog::channel($channel,$tag);
        return LogFileLock::run($this->directory,function()use($channel,$tag,$revision,$onlyIfDue,$useSaved):array{
            $state=$this->read();$now=$this->clock?($this->clock)():time();
            if(!is_int($now)||$now<1)throw new \RuntimeException('Update clock unavailable');
            if($revision!==null&&$revision!==$state['revision'])throw new HttpException(409,'UPDATE_STATE_CHANGED');
            if($onlyIfDue&&$state['checked_at']!==null&&$now>=$state['checked_at']&&$now-$state['checked_at']<($state['error']===null?86400:3600))return $state;
            if($state['revision']>=9007199254740990)throw new HttpException(503,'UPDATE_CHECK_FAILED');
            $channel??=$state['channel'];$tag=$channel==='custom'?($useSaved?$state['custom_tag']:$tag):'';
            ReleaseCatalog::channel($channel,$tag);
            if($this->audit)($this->audit)(['before_channel'=>$state['channel'],'before_tag'=>$state['custom_tag'],'requested_channel'=>$channel,'requested_tag'=>$tag,'revision'=>$state['revision']+1]);
            $state=array_replace($state,['channel'=>$channel,'custom_tag'=>$tag,'checked_at'=>$now,'revision'=>$state['revision']+1,'release'=>null,'available'=>null,'error'=>null]);
            try{
                $release=ReleaseCatalog::select($this->source->releases(),$channel,$tag);
                $comparison=$release?ReleaseCatalog::compare($release['tag'],$this->current):null;
                $state['release']=$release;$state['available']=$release!==null&&($comparison===null?$release['tag']!==$this->current:$comparison>0);
            }catch(\Throwable $error){
                $code=$error instanceof HttpException?$error->errorCode:'UPDATE_CHECK_FAILED';
                $state['error']=in_array($code,self::errors(),true)?$code:'UPDATE_CHECK_FAILED';
                $this->publish($state);
                throw new HttpException($error instanceof HttpException?$error->status:502,$state['error']);
            }
            $this->publish($state);return $state;
        });
    }
    private static function errors(): array {return ['UPDATE_SOURCE_NOT_FOUND','UPDATE_RATE_LIMITED','UPDATE_SOURCE_UNAVAILABLE','INVALID_UPDATE_RELEASE','UPDATE_RELEASE_LIMIT','UPDATE_CHECK_FAILED'];}
    private function initial(): array {return ['repository'=>$this->repository,'current_version'=>$this->current,'channel'=>$this->channel,'custom_tag'=>$this->tag,'revision'=>0,'checked_at'=>null,'release'=>null,'available'=>null,'error'=>null];}
    private function read(): array
    {
        $path=$this->directory.'/check.json';
        if(is_link($path))throw new \RuntimeException('Update state cannot be a link');
        if(!is_file($path))return $this->initial();
        if(filesize($path)>16384)throw new HttpException(503,'UPDATE_STATE_INVALID');
        try{
            $value=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
            if(!is_array($value)||!is_string($value['identity']??null))throw new \RuntimeException();
            if(!hash_equals($this->identity,$value['identity']))return $this->initial();
            $state=$value['state']??null;
            if(!is_array($state)||array_diff(array_keys($state),array_keys($this->initial()))||count($state)!==count($this->initial())||$state['repository']!==$this->repository||$state['current_version']!==$this->current
                ||!is_string($state['channel'])||!is_string($state['custom_tag'])||!is_int($state['revision'])||$state['revision']<1||$state['revision']>9007199254740990||!is_int($state['checked_at'])||$state['checked_at']<1)throw new \RuntimeException();
            ReleaseCatalog::channel($state['channel'],$state['custom_tag']);
            if($state['error']!==null){if(!in_array($state['error'],self::errors(),true)||$state['available']!==null||$state['release']!==null)throw new \RuntimeException();}
            else{
                if(!is_bool($state['available']))throw new \RuntimeException();
                if($state['release']!==null){
                    $r=$state['release'];if(!is_array($r)||count($r)!==4||!isset($r['id'],$r['tag'],$r['prerelease'],$r['published_at']))throw new \RuntimeException();
                    $clean=ReleaseCatalog::select([['id'=>$r['id'],'tag_name'=>$r['tag'],'prerelease'=>$r['prerelease'],'published_at'=>$r['published_at'],'draft'=>false]],$state['channel'],$state['custom_tag']);
                    if($clean!==$r)throw new \RuntimeException();
                    $comparison=ReleaseCatalog::compare($r['tag'],$this->current);
                    if($state['available']!==($comparison===null?$r['tag']!==$this->current:$comparison>0))throw new \RuntimeException();
                }elseif($state['available'])throw new \RuntimeException();
            }
            return $state;
        }catch(\Throwable){throw new HttpException(503,'UPDATE_STATE_INVALID');}
    }
    private function publish(array $state): void
    {
        $contents=json_encode(['identity'=>$this->identity,'state'=>$state],JSON_THROW_ON_ERROR);
        $temporary=tempnam($this->directory,'check-');if($temporary===false)throw new \RuntimeException('Update state unavailable');
        try{if(file_put_contents($temporary,$contents)!==strlen($contents)||!chmod($temporary,0600)||!rename($temporary,$this->directory.'/check.json'))throw new \RuntimeException('Update state publication failed');}
        finally{if(is_file($temporary))unlink($temporary);}
    }
}
