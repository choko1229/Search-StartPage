<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth\Auth;
use App\Http\{Request,Response,HttpException};
use App\Repositories\BackgroundRepository;
use App\Services\{BackgroundInput,BackgroundUpload,BackgroundCompression,BackgroundFileResponse,SyncInput};

final class BackgroundController
{
    public function __construct(private readonly Auth $auth,private readonly BackgroundRepository $repository,private readonly BackgroundUpload $storage) {}
    private function owner(Request $request): int
    {
        $user=(int)$this->auth->requireUser()['id'];
        if(isset($request->body['user_id'])&&(!is_scalar($request->body['user_id'])||(string)$request->body['user_id']!==(string)$user))throw new HttpException(403,'SYNC_OWNER_MISMATCH');
        return $user;
    }
    public static function present(array $row): array
    {
        return (array)json_decode($row['settings_json'],false,32,JSON_THROW_ON_ERROR)+[
            'id'=>$row['id'],'name'=>$row['name'],'type'=>$row['type'],'sourceType'=>$row['source_type'],
            'url'=>$row['source_type']==='upload'?'/api/backgrounds/'.$row['id'].'/file':($row['external_url']??''),
            'fileSize'=>(int)$row['file_size'],'cloudSync'=>(bool)$row['cloud_sync'],'deleted'=>(bool)$row['deleted'],
            'version'=>(int)$row['version'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at']];
    }
    public function index(Request $request): Response
    {
        $user=$this->owner($request);return Response::json(['items'=>array_map(self::present(...),$this->repository->list($user)),
            'storage'=>$this->repository->usage($user),'compression'=>BackgroundCompression::capabilities()]);
    }
    public function url(Request $request): Response
    {
        $user=$this->owner($request);$input=$request->jsonObject?->item;
        if(!is_object($input))throw new HttpException(422,'INVALID_BACKGROUND');
        return Response::json(['item'=>self::present($this->repository->save($user,BackgroundInput::validate($input)))],201);
    }
    public function upload(Request $request): Response
    {
        $user=$this->owner($request);$raw=$request->input('item');
        if(strlen($raw)>65536)throw new HttpException(422,'INVALID_BACKGROUND');
        try{$input=json_decode($raw,false,24,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new HttpException(422,'INVALID_BACKGROUND');}
        if(!is_object($input))throw new HttpException(422,'INVALID_BACKGROUND');
        $item=BackgroundInput::validate($input,true);$staged=null;$compressed=null;$committed=false;
        try {
            $staged=$this->storage->receive($user,$request->files['file']??[]);
            $compressed=(new BackgroundCompression())->compress($staged['path']);
            $item['type']=$compressed['type'];
            $saved=$this->repository->save($user,$item,null,$compressed);$committed=true;
            return Response::json(['item'=>self::present($saved),'storage'=>$this->repository->usage($user),'warning'=>$compressed['warning']],201);
        } finally {
            if($staged!==null && (!$committed || ($compressed!==null&&$compressed['path']!==$staged['path'])))@unlink($staged['path']);
            if(!$committed && $compressed!==null && $compressed['path']!==($staged['path']??null))@unlink($compressed['path']);
        }
    }
    public function update(Request $request,array $params): Response
    {
        $user=$this->owner($request);$before=$this->repository->find($user,$params['id']);if(!$before)throw new HttpException(404,'NOT_FOUND');
        $patch=$request->jsonObject?->item;if(!is_object($patch))throw new HttpException(422,'INVALID_BACKGROUND');
        if(isset($patch->id)&&$patch->id!==$params['id'])throw new HttpException(422,'INVALID_BACKGROUND');
        $item=BackgroundInput::validate((object)array_replace(self::present($before),(array)$patch),$before['source_type']==='upload');
        return Response::json(['item'=>self::present($this->repository->save($user,$item,SyncInput::version($request,$user)))]);
    }
    public function delete(Request $request,array $params): Response
    {
        $user=$this->owner($request);$before=$this->repository->find($user,$params['id']);if(!$before)throw new HttpException(404,'NOT_FOUND');
        $item=BackgroundInput::validate((object)array_replace(self::present($before),['deleted'=>true]),$before['source_type']==='upload');
        return Response::json(['item'=>self::present($this->repository->save($user,$item,SyncInput::version($request,$user)))]);
    }
    public function file(Request $request,array $params): Response
    {
        $user=$this->owner($request);$row=$this->repository->find($user,$params['id']);
        if(!$row||$row['deleted']||$row['source_type']!=='upload')throw new HttpException(404,'NOT_FOUND');
        return BackgroundFileResponse::create($request,$this->storage->existingPath($user,$row['file_path']),$row['mime']);
    }
}
