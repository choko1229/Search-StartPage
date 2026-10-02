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
        if(array_key_exists('user_id',$request->body)&&(!is_scalar($request->body['user_id'])||(string)$request->body['user_id']!==(string)$user))throw new HttpException(403,'SYNC_OWNER_MISMATCH');
        if(array_key_exists('HTTP_X_BACKGROUND_OWNER',$request->server)&&$request->server['HTTP_X_BACKGROUND_OWNER']!==(string)$user)throw new HttpException(403,'SYNC_OWNER_MISMATCH');
        return $user;
    }
    public static function present(array $row): array
    {
        return (array)json_decode($row['settings_json'],false,32,JSON_THROW_ON_ERROR)+[
            'id'=>$row['id'],'name'=>$row['name'],'type'=>$row['type'],'sourceType'=>$row['source_type'],
            'url'=>$row['source_type']==='upload'?'/api/backgrounds/'.$row['id'].'/file':($row['external_url']??''),
            'fileSize'=>(int)$row['file_size'],'fileRevision'=>$row['file_path']===null?null:hash('sha256',$row['file_path']),'cloudSync'=>(bool)$row['cloud_sync'],'deleted'=>(bool)$row['deleted'],
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
    public function upload(Request $request,array $params=[]): Response
    {
        $user=$this->owner($request);$raw=$request->input('item');
        if(strlen($raw)>65536)throw new HttpException(422,'INVALID_BACKGROUND');
        try{$input=json_decode($raw,false,24,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new HttpException(422,'INVALID_BACKGROUND');}
        if(!is_object($input))throw new HttpException(422,'INVALID_BACKGROUND');
        $before=null;$expected=null;
        $requestId=$request->server['HTTP_X_BACKGROUND_REQUEST']??null;
        if($requestId!==null&&(!is_string($requestId)||!preg_match('/^[a-f0-9]{64}$/D',$requestId)))throw new HttpException(422,'INVALID_INPUT');
        if(isset($params['id'])) {
            $before=$this->repository->find($user,$params['id']);if(!$before)throw new HttpException(404,'NOT_FOUND');
            if(isset($input->id)&&$input->id!==$params['id'])throw new HttpException(422,'INVALID_BACKGROUND');
            $version=$request->input('version');
            if(!preg_match('/^(?:0|[1-9][0-9]{0,15})$/D',$version)||(float)$version>9007199254740990)throw new HttpException(422,'INVALID_INPUT');
            $expected=(int)$version;
            if($requestId===null&&(int)$before['version']!==$expected)throw new HttpException(409,'BACKGROUND_CONFLICT');
            $input=(object)array_replace(self::present($before),(array)$input);
        }
        $staged=null;$compressed=null;$committed=false;
        try {
            $staged=$this->storage->receive($user,$request->files['file']??[]);
            $receipt=$requestId===null?null:['id'=>$requestId,'fingerprint'=>hash('sha256',json_encode([$params['id']??null,$expected,$raw,hash_file('sha256',$staged['path'])],JSON_THROW_ON_ERROR))];
            if($receipt!==null) {
                $replayed=$this->repository->uploadReceipt($user,$receipt['id'],$receipt['fingerprint']);
                if($replayed!==null)return Response::json(['item'=>self::present($replayed),'storage'=>$this->repository->usage($user),'warning'=>$replayed['_upload_warning']??null,'replayed'=>true],$before===null?201:200);
            }
            $item=BackgroundInput::validate($input,true);
            $compressed=(new BackgroundCompression())->compress($staged['path']);
            $item['type']=$compressed['type'];
            $saved=$this->repository->save($user,$item,$expected,$compressed,false,$receipt);
            if(isset($saved['_upload_replayed']))return Response::json(['item'=>self::present($saved),'storage'=>$this->repository->usage($user),'warning'=>$saved['_upload_warning']??null,'replayed'=>true],$expected===null?201:200);
            $committed=true;
            if($before!==null&&$before['file_path']!==null) {
                try{@unlink($this->storage->existingPath($user,$before['file_path']));}catch(HttpException){}
            }
            return Response::json(['item'=>self::present($saved),'storage'=>$this->repository->usage($user),'warning'=>$compressed['warning']],$before===null?201:200);
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
        if(property_exists($patch,'sourceType')&&(!in_array($patch->sourceType,['url','upload'],true)||($patch->sourceType==='upload'&&$before['source_type']!=='upload')))throw new HttpException(422,'INVALID_BACKGROUND');
        $clearFile=isset($patch->sourceType)&&$patch->sourceType==='url';
        if($clearFile&&$before['source_type']==='upload'&&!property_exists($patch,'url'))$patch->url='';
        $item=BackgroundInput::validate((object)array_replace(self::present($before),(array)$patch),$before['source_type']==='upload'&&!$clearFile);
        $saved=$this->repository->save($user,$item,SyncInput::version($request,$user),null,$clearFile);
        if($clearFile&&$before['file_path']!==null){try{@unlink($this->storage->existingPath($user,$before['file_path']));}catch(HttpException){}}
        return Response::json(['item'=>self::present($saved)]);
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
        return BackgroundFileResponse::create($request,$this->storage->existingPath($user,$row['file_path']),$row['mime'],hash('sha256',$row['file_path']));
    }
}
