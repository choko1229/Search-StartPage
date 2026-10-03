<?php
declare(strict_types=1);
namespace App\Services;

use App\Http\HttpException;

final class GitHubReleases
{
    public function __construct(private readonly string $repository,private readonly ?\Closure $transport=null,private readonly string $token=''){
        ReleaseCatalog::repository($repository);
        if($token!==''&&!preg_match('/^[A-Za-z0-9_.-]{1,512}$/D',$token))throw new HttpException(422,'INVALID_UPDATE_CONFIGURATION');
    }

    public function releases(): array
    {
        $releases=[];
        for($page=1;$page<=20;$page++){
            $url='https://api.github.com/repos/'.$this->repository.'/releases?per_page=100&page='.$page;
            $headers=['Accept'=>'application/vnd.github+json','User-Agent'=>'search-startpage-update-check','X-GitHub-Api-Version'=>'2022-11-28'];
            if($this->token!=='')$headers['Authorization']='Bearer '.$this->token;
            $response=$this->transport?($this->transport)($url,$headers):$this->request($url,$headers);
            $status=$response['status']??0;
            if($status===404)throw new HttpException(502,'UPDATE_SOURCE_NOT_FOUND');
            if($status===429||($status===403&&($response['remaining']??null)==='0'))throw new HttpException(503,'UPDATE_RATE_LIMITED');
            if($status!==200)throw new HttpException(502,'UPDATE_SOURCE_UNAVAILABLE');
            $body=$response['body']??null;
            if(!is_string($body)||strlen($body)>2*1024*1024||!str_starts_with(ltrim($body),'['))throw new HttpException(502,'INVALID_UPDATE_RELEASE');
            try{$rows=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new HttpException(502,'INVALID_UPDATE_RELEASE');}
            if(!is_array($rows)||!array_is_list($rows)||count($rows)>100)throw new HttpException(502,'INVALID_UPDATE_RELEASE');
            $releases=array_merge($releases,$rows);
            if(count($rows)<100)return $releases;
        }
        throw new HttpException(502,'UPDATE_RELEASE_LIMIT');
    }
    private function request(string $url,array $headers): array
    {
        $header=implode("\r\n",array_map(static fn($key,$value)=>$key.': '.$value,array_keys($headers),array_values($headers)));
        $context=stream_context_create(['http'=>['method'=>'GET','header'=>$header,'timeout'=>15,'follow_location'=>0,'ignore_errors'=>true],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $stream=@fopen($url,'rb',false,$context);
        if($stream===false)throw new HttpException(502,'UPDATE_SOURCE_UNAVAILABLE');
        try{$body=stream_get_contents($stream,2*1024*1024+1);}finally{fclose($stream);}
        $status=0;$remaining=null;
        foreach($http_response_header??[] as $header){
            if(preg_match('~^HTTP/\S+ (\d{3})~',$header,$match))$status=(int)$match[1];
            if(preg_match('/^x-ratelimit-remaining:\s*(\d+)\s*$/i',$header,$match))$remaining=$match[1];
        }
        return ['status'=>$status,'body'=>$body,'remaining'=>$remaining];
    }
}
