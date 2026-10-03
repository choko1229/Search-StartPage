<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth\Auth;
use App\Config;
use App\Helpers\View;
use App\Http\{Request,Response,HttpException};
use App\Repositories\SitePolicyRepository;
use App\Services\{SitePolicy,PolicyState,AdminAuditLogger};

final class AdminPolicyController
{
    public function __construct(private readonly SitePolicyRepository $repository,private readonly PolicyState $state,private readonly Auth $auth,private readonly AdminAuditLogger $audit,private readonly View $view,private readonly Config $config) {}
    public function read(Request $request): Response
    {
        $data=$this->repository->read();$data['effective']=SitePolicy::effective($data['policy'],$this->config);
        return $request->isApi() ? Response::json($data) : new Response($this->view->render('admin-policy',$data));
    }
    public function update(Request $request): Response
    {
        $version=$request->body['version'] ?? null;
        if(!$request->isApi()) {
            if(!is_string($version)||!preg_match('/^[1-9][0-9]{0,17}$/D',$version))throw new HttpException(422,'INVALID_INPUT');
            $version=(int)$version;$flags=[];$limits=[];
            foreach(SitePolicy::FLAGS as $key){$value=$request->body[$key] ?? null;if(!in_array($value,['0','1'],true))throw new HttpException(422,'INVALID_INPUT');$flags[$key]=$value==='1';}
            foreach(SitePolicy::LIMITS as $key=>[$min,$max]){$value=$request->body[$key] ?? null;if(!is_string($value)||($value!==''&&!preg_match('/^(0|[1-9][0-9]{0,15})$/D',$value)))throw new HttpException(422,'INVALID_INPUT');$limits[$key]=$value==='' ? null : (int)$value;}
            $policy=['flags'=>$flags,'limits'=>$limits];
        }else $policy=$request->jsonObject?->policy ?? null;
        if(!is_int($version)||$version<1||$version>=PHP_INT_MAX)throw new HttpException(422,'INVALID_INPUT');
        $result=$this->repository->update(SitePolicy::validate($policy),$version,(int)$this->auth->requireUser()['id'],$this->state);
        $this->audit->flush();
        return $request->isApi() ? Response::json($result) : Response::redirect('/admin/policy');
    }
}
