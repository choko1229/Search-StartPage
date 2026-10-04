<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Helpers\View;
use App\Http\{Request,Response,HttpException};
use App\Services\UpdateChecks;

final class AdminUpdatesController
{
    public function __construct(private readonly UpdateChecks $checks,private readonly View $view){}
    public function read(Request $request): Response
    {
        $state=$this->checks->status();
        return $request->isApi()?Response::json($state):new Response($this->view->render('admin-update',$state));
    }
    public function check(Request $request): Response
    {
        $channel=$request->body['channel']??null;$tag=$request->body['custom_tag']??'';$revision=$request->body['revision']??null;
        if(!$request->isApi()&&is_string($revision)&&preg_match('/^(0|[1-9][0-9]{0,15})$/D',$revision))$revision=(int)$revision;
        if(!is_string($channel)||!is_string($tag)||!is_int($revision)||$revision<0||$revision>9007199254740990)throw new HttpException(422,'INVALID_INPUT');
        try{$result=$this->checks->check($channel,$tag,$revision);}
        catch(HttpException $error){
            if($request->isApi()||in_array($error->status,[409,422],true))throw $error;
            return new Response($this->view->render('admin-update',$this->checks->status()),$error->status,errorCode:$error->errorCode);
        }
        return $request->isApi()?Response::json($result):Response::redirect('/admin/update');
    }
}
