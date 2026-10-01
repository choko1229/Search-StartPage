<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth\Auth;
use App\Http\{Request,Response,HttpException};
use App\Repositories\SyncRepository;
use App\Services\{CloudMutation,SyncInput,SyncRetention};
final class CloudDataController
{
    public function __construct(private readonly Auth $auth,private readonly SyncRepository $repository) {}
    public function handle(Request $request,array $params,string $collection,string $action): Response
    {
        $user=(int)$this->auth->requireUser()['id'];
        if($action==='read') {
            $current=SyncRetention::read($this->repository,$user);$value=$current['document']->$collection??(object)[];
            $items=$collection==='settings'?$value:array_values((array)$value);
            if($collection==='history')usort($items,static fn($a,$b)=>$b->at<=>$a->at);
            if(in_array($collection,['favorite-folders','providers-web','providers-ai'],true))usort($items,static fn($a,$b)=>($a->sortOrder??0)<=>($b->sortOrder??0));
            return Response::json(['version'=>$current['version'],($collection==='settings'?'settings':'items')=>$items]);
        }
        $version=SyncInput::version($request,$user);$current=$this->repository->read($user);
        if($current['version']!==$version)return SyncController::conflict($current);
        $input=$collection==='settings'?($request->jsonObject?->settings??null):($request->jsonObject?->item??null);
        if($input!==null && !is_object($input))throw new HttpException(422,'INVALID_INPUT');
        $document=CloudMutation::apply($current['document'],$collection,$action,$params['id']??null,$input);
        $saved=$this->repository->write($user,$version,$document);
        return $saved===null?SyncController::conflict($this->repository->read($user)):Response::json($saved,$action==='create'?201:200);
    }
}
