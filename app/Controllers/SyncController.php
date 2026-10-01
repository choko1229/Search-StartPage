<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth\Auth;
use App\Http\{Request,Response,HttpException};
use App\Repositories\SyncRepository;
use App\Services\SyncDocument;

final class SyncController
{
    public function __construct(private readonly Auth $auth,private readonly SyncRepository $repository) {}
    public function read(Request $request): Response
    {
        $user=$this->auth->requireUser();return Response::json($this->repository->read((int)$user['id']));
    }
    public function write(Request $request): Response
    {
        $user=$this->auth->requireUser();$version=$request->body['version']??null;
        if(!is_int($version) || $version<0 || $version>9007199254740990)throw new HttpException(422,'INVALID_INPUT');
        // Request decodes objects as arrays; preserve empty maps and their types
        // through the raw JSON document, captured independently by the request.
        $document=SyncDocument::validate($request->jsonObject?->document ?? null);
        $result=$this->repository->write((int)$user['id'],$version,$document);
        if($result===null)return new Response(json_encode(['success'=>false,'error'=>['code'=>'SYNC_CONFLICT','message'=>'SYNC_CONFLICT'],'data'=>$this->repository->read((int)$user['id'])],JSON_THROW_ON_ERROR),409,['Content-Type'=>'application/json; charset=utf-8']);
        return Response::json($result);
    }
}
