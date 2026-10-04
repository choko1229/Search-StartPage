<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Fixed GitHub endpoints; transport receives a bounded streaming body consumer. */
final class GitHubUpdateAsset
{
    public const LIMIT=UpdateManifest::TOTAL_LIMIT+12582912;
    public function __construct(private readonly string $repository,private readonly string $token='',private readonly ?\Closure $transport=null)
    {
        ReleaseCatalog::repository($repository);
        if($token!==''&&!preg_match('/^[A-Za-z0-9_.-]{1,512}$/D',$token))throw new HttpException(422,'INVALID_UPDATE_CONFIGURATION');
    }
    private function headers(bool $binary,bool $authenticated): array
    {
        $headers=['Accept'=>$binary?'application/octet-stream':'application/vnd.github+json','User-Agent'=>'search-startpage-updater','X-GitHub-Api-Version'=>'2022-11-28'];
        if($authenticated&&$this->token!=='')$headers['Authorization']='Bearer '.$this->token;
        return $headers;
    }
    private function request(string $url,array $headers,\Closure $consume): array
    {
        if($this->transport)return ($this->transport)($url,$headers,$consume);
        if(!extension_loaded('curl'))throw new HttpException(503,'UPDATE_TRANSPORT_UNAVAILABLE');
        $handle=curl_init($url);$status=0;$location=null;$remaining=null;$headerBytes=0;$discarded=0;$failure=null;
        if($handle===false)throw new HttpException(502,'UPDATE_SOURCE_UNAVAILABLE');
        curl_setopt_array($handle,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>180,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_LOW_SPEED_LIMIT=>1024,CURLOPT_LOW_SPEED_TIME=>30,
            CURLOPT_HTTPHEADER=>array_map(static fn($key,$value)=>$key.': '.$value,array_keys($headers),array_values($headers)),
            CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$status,&$location,&$remaining,&$headerBytes):int{
                $headerBytes+=strlen($line);if($headerBytes>65536)return 0;
                if(preg_match('~^HTTP/\S+ (\d{3})~',$line,$match)){$status=(int)$match[1];$location=null;$remaining=null;}
                if(preg_match('/^location:\s*(.*?)\s*$/i',$line,$match)){$location=$location===null?$match[1]:'';}
                if(preg_match('/^x-ratelimit-remaining:\s*(\d+)\s*$/i',$line,$match))$remaining=$match[1];
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION=>static function($curl,string $chunk)use(&$failure,&$status,&$discarded,$consume):int{
                if($status!==200){$discarded+=strlen($chunk);return $discarded>2097152?0:strlen($chunk);}
                try{$consume($chunk);return strlen($chunk);}catch(\Throwable $error){$failure=$error;return 0;}
            }]);
        try{$ok=curl_exec($handle);if($failure)throw $failure;if($ok===false)throw new HttpException(502,'UPDATE_SOURCE_UNAVAILABLE');}
        finally{curl_close($handle);}
        return ['status'=>$status,'location'=>$location,'remaining'=>$remaining];
    }
    private static function status(array $response): void
    {
        $status=$response['status']??0;
        if($status===404)throw new HttpException(502,'UPDATE_SOURCE_NOT_FOUND');
        if($status===429||($status===403&&($response['remaining']??null)==='0'))throw new HttpException(503,'UPDATE_RATE_LIMITED');
        if($status!==200)throw new HttpException(502,'UPDATE_SOURCE_UNAVAILABLE');
    }
    public function select(int $releaseId): array
    {
        if($releaseId<1)throw new HttpException(422,'INVALID_UPDATE_RELEASE');
        $selected=null;$seen=[];
        for($page=1;$page<=20;$page++){
            $body='';$response=$this->request('https://api.github.com/repos/'.$this->repository.'/releases/'.$releaseId.'/assets?per_page=100&page='.$page,$this->headers(false,true),static function(string $chunk)use(&$body):void{
                if(strlen($body)+strlen($chunk)>2097152)throw new HttpException(502,'INVALID_UPDATE_ASSET');$body.=$chunk;
            });
            self::status($response);
            try{$rows=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new HttpException(502,'INVALID_UPDATE_ASSET');}
            if(!str_starts_with(ltrim($body),'[')||!is_array($rows)||!array_is_list($rows)||count($rows)>100)throw new HttpException(502,'INVALID_UPDATE_ASSET');
            foreach($rows as $row){
                if(!is_array($row)||!is_int($row['id']??null)||$row['id']<1||!is_string($row['name']??null)||strlen($row['name'])>255||isset($seen[$row['id']]))throw new HttpException(502,'INVALID_UPDATE_ASSET');
                $seen[$row['id']]=true;
                if($row['name']!=='search-startpage.tar')continue;
                if($selected!==null)throw new HttpException(502,'AMBIGUOUS_UPDATE_ASSET');
                $selected=self::validate($row);
            }
            if(count($rows)<100){if($selected===null)throw new HttpException(502,'UPDATE_ASSET_NOT_FOUND');return $selected;}
        }
        throw new HttpException(502,'UPDATE_ASSET_LIMIT');
    }
    private static function validate(array $asset): array
    {
        if(!is_int($asset['id']??null)||$asset['id']<1||($asset['name']??null)!=='search-startpage.tar'||($asset['state']??null)!=='uploaded'
            ||!is_int($asset['size']??null)||$asset['size']<1536||$asset['size']>self::LIMIT||!is_string($asset['digest']??null)
            ||!preg_match('/^sha256:[a-f0-9]{64}$/D',$asset['digest']))throw new HttpException(502,'INVALID_UPDATE_ASSET');
        return ['id'=>$asset['id'],'name'=>$asset['name'],'state'=>'uploaded','size'=>$asset['size'],'digest'=>$asset['digest']];
    }
    private static function redirect(mixed $url): string
    {
        if(!is_string($url)||strlen($url)>8192||preg_match('/[\x00-\x20\x7f\\\\]/',$url))throw new HttpException(502,'INVALID_UPDATE_REDIRECT');
        $parts=parse_url($url);
        if(!$parts||($parts['scheme']??null)!=='https'||!in_array($parts['host']??'', ['release-assets.githubusercontent.com','objects.githubusercontent.com'],true)
            ||isset($parts['port'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||!str_starts_with($parts['path']??'','/'))throw new HttpException(502,'INVALID_UPDATE_REDIRECT');
        return $url;
    }
    public function prepare(int $releaseId,string $version,string $archive,string $stage): array
    {
        $asset=$this->select($releaseId);$this->download($asset,$archive);
        try{return (new UpdatePackage())->verify($archive,$stage,$version,true);}
        catch(\Throwable $error){if(!unlink($archive))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');throw $error;}
    }
    /** New private archive only. Failed transfers never leave a usable archive. */
    public function download(array $asset,string $archive): array
    {
        $asset=self::validate($asset);UpdatePackagePaths::directory(dirname($archive));
        if(file_exists($archive)||is_link($archive))throw new HttpException(409,'UPDATE_PACKAGE_EXISTS');
        $stream=@fopen($archive,'xb');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        $success=false;$bytes=0;$hash=hash_init('sha256');
        try{
            if(!chmod($archive,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $consume=static function(string $chunk)use($asset,$stream,&$bytes,$hash):void{
                $length=strlen($chunk);if($bytes+$length>$asset['size'])throw new HttpException(502,'UPDATE_ASSET_SIZE_MISMATCH');
                $offset=0;while($offset<$length){$written=fwrite($stream,substr($chunk,$offset));if($written===false||$written===0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset+=$written;}
                hash_update($hash,$chunk);$bytes+=$length;
            };
            $response=$this->request('https://api.github.com/repos/'.$this->repository.'/releases/assets/'.$asset['id'],$this->headers(true,true),$consume);
            if(($response['status']??0)===302){
                if($bytes!==0)throw new HttpException(502,'INVALID_UPDATE_REDIRECT');
                $response=$this->request(self::redirect($response['location']??null),$this->headers(true,false),$consume);
            }
            self::status($response);
            if($bytes!==$asset['size'])throw new HttpException(502,'UPDATE_ASSET_SIZE_MISMATCH');
            $digest=hash_final($hash);if(!hash_equals(substr($asset['digest'],7),$digest))throw new HttpException(502,'UPDATE_ASSET_HASH_MISMATCH');
            if(!fflush($stream))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $success=true;return ['bytes'=>$bytes,'sha256'=>$digest];
        }finally{
            fclose($stream);
            if(!$success&&!unlink($archive))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        }
    }
}
