<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Helpers\View;
use App\Http\{Request,Response,HttpException};
use App\Services\UpdateChecks;
use App\Repositories\UpdateHistoryRepository;

final class AdminUpdatesController
{
    public function __construct(private readonly UpdateChecks $checks,private readonly View $view,private readonly UpdateHistoryRepository $history,
        private readonly \App\Services\UpdateRequests $requests,private readonly \Closure $actor){}
    private function data():array {return [...$this->checks->status(),'history'=>$this->history->listing(),'execution'=>$this->requests->status()];}
    public function read(Request $request): Response
    {
        $state=$this->data();
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
            return new Response($this->view->render('admin-update',$this->data()),$error->status,errorCode:$error->errorCode);
        }
        return $request->isApi()?Response::json([...$result,'history'=>$this->history->listing()]):Response::redirect('/admin/update');
    }
    public function apply(Request $request):Response {return $this->accept($request,'apply');}
    public function rollback(Request $request):Response {return $this->accept($request,'rollback');}
    public function submit(Request $request):Response
    {
        // Preserve the existing check payload while supporting the specified update API.
        foreach(['command_revision','check_revision','engine_revision'] as $key)if(array_key_exists($key,$request->body))return $this->apply($request);
        return $this->check($request);
    }
    private function accept(Request $request,string $operation):Response
    {
        $values=$request->body;if(!$request->isApi())unset($values['_csrf']);$keys=array_keys($values);sort($keys);
        if($keys!==['check_revision','command_revision','engine_revision'])throw new HttpException(422,'INVALID_INPUT');
        foreach($values as &$value){
            if(!$request->isApi()&&is_string($value)&&preg_match('/^(0|[1-9][0-9]{0,15})$/D',$value))$value=(int)$value;
            if(!is_int($value)||$value<0||$value>9007199254740990)throw new HttpException(422,'INVALID_INPUT');
        }unset($value);
        $state=$this->requests->enqueue(($this->actor)(),$operation,$values['command_revision'],$values['check_revision'],$values['engine_revision']);
        return $request->isApi()?Response::json(['execution'=>$state,'history'=>$this->history->listing()],202):Response::redirect('/admin/update');
    }
}
