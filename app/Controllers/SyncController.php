<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth\Auth;
use App\Http\{Request,Response,HttpException};
use App\Repositories\SyncRepository;
use App\Services\{SyncDocument,SyncInput,SyncMerge,SyncRetention};

final class SyncController
{
    public function __construct(private readonly Auth $auth,private readonly SyncRepository $repository) {}
    public function read(Request $request): Response
    {
        $user=$this->auth->requireUser();return Response::json(SyncRetention::read($this->repository,(int)$user['id']));
    }
    public function write(Request $request): Response
    {
        $user=$this->auth->requireUser();$version=SyncInput::version($request,(int)$user['id']);
        // Keep empty maps and document types from the single JSON decode.
        $document=SyncDocument::validate($request->jsonObject?->document ?? null);
        $result=$this->repository->write((int)$user['id'],$version,$document);
        if($result===null)return self::conflict($this->repository->read((int)$user['id']));
        return Response::json($result);
    }
    public static function conflict(array $current): Response
    {
        return new Response(json_encode(['success'=>false,'error'=>['code'=>'SYNC_CONFLICT','message'=>'SYNC_CONFLICT'],'data'=>$current],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),409,['Content-Type'=>'application/json; charset=utf-8'],errorCode:'SYNC_CONFLICT');
    }
    public function resolve(Request $request): Response
    {
        $user=(int)$this->auth->requireUser()['id'];$version=SyncInput::version($request,$user);$current=$this->repository->read($user);
        if($current['version']!==$version)return self::conflict($current);
        $previous=SyncDocument::validate($request->jsonObject?->previous??null);$local=SyncDocument::validate($request->jsonObject?->local??null);
        $choices=$request->jsonObject?->choices??(object)[];$rules=$request->jsonObject?->rules??($current['document']->settings->syncRules??(object)[]);
        if(!is_object($choices) || !is_object($rules))throw new HttpException(422,'INVALID_INPUT');
        foreach($rules as $choice)if(!in_array($choice,['local','cloud'],true))throw new HttpException(422,'INVALID_INPUT');
        $merged=(new SyncMerge())->merge($previous,$local,$current['document'],(object)array_replace((array)$rules,(array)$choices));
        if($merged['conflicts'])return self::conflict($current+['conflicts'=>$merged['conflicts']]);
        $document=$merged['document'];
        if(count((array)$rules)) {$document->settings??=(object)[];$document->settings->syncRules=(object)array_replace((array)($document->settings->syncRules??(object)[]),(array)$rules);}
        SyncDocument::validate($document);$saved=$this->repository->write($user,$version,$document);
        return $saved===null?self::conflict($this->repository->read($user)):Response::json($saved+['rules'=>$document->settings->syncRules??(object)[]]);
    }
}
