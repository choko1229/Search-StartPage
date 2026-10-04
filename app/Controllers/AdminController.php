<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Helpers\View;
use App\Http\{Request,Response,HttpException};
use App\Repositories\AdminRepository;
use App\Repositories\AdminSettingsRepository;
use App\Services\{BackgroundCompression,AdminAuditLogger};
use App\Auth\Auth;

final class AdminController
{
    public function __construct(private readonly AdminRepository $repository, private readonly View $view,
        private readonly AdminSettingsRepository $settings,private readonly Auth $auth,private readonly AdminAuditLogger $audit,
        private readonly \App\Repositories\AdminRoleRepository $roles,private readonly ?\Closure $storageLimit=null,private readonly ?\Closure $updates=null) {}

    private function overview(): array
    {
        $update=null;
        if($this->updates){try{$update=($this->updates)();}catch(\Throwable){$update=['available'=>null,'error'=>'UPDATE_STATE_INVALID'];}}
        return ['counts'=>$this->repository->dashboard(), 'compression'=>BackgroundCompression::capabilities(),'update'=>$update];
    }
    public function page(Request $request): Response
    {
        return new Response($this->view->render('admin', $this->overview()));
    }
    public function dashboard(Request $request): Response
    {
        return Response::json($this->overview());
    }

    private function listing(Request $request, bool $storage): array
    {
        $search = $request->query['q'] ?? '';
        if (!is_string($search) || !mb_check_encoding($search,'UTF-8') || mb_strlen($search)>100 || preg_match('/[\x00-\x1f\x7f]/u',$search)) throw new HttpException(422,'INVALID_INPUT');
        $numbers = [];
        foreach (['page'=>[1,1000000,1],'page_size'=>[1,100,25]] as $key=>[$min,$max,$default]) {
            $value = $request->query[$key] ?? (string)$default;
            if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,6}$/D',$value) || (int)$value<$min || (int)$value>$max) throw new HttpException(422,'INVALID_INPUT');
            $numbers[$key]=(int)$value;
        }
        $version=$this->roles->version();
        $result=$this->repository->users(trim($search),$numbers['page'],$numbers['page_size'],$storage)+['role_version'=>$version];
        if($storage){
            $limit=$this->storageLimit?($this->storageLimit)():0;
            $result['storage_summary']=$this->repository->storageTotals()+['limit_bytes'=>$limit>0?$limit:null];
            foreach($result['items'] as &$item)$item['over_limit']=$limit>0&&$item['stored_background_bytes']>$limit;
            unset($item);
        }
        return $result;
    }
    public function users(Request $request): Response { return Response::json($this->listing($request,false)); }
    public function storage(Request $request): Response { return Response::json($this->listing($request,true)); }
    public function usersPage(Request $request): Response { return $this->listPage($request,false); }
    public function storagePage(Request $request): Response { return $this->listPage($request,true); }
    private function listPage(Request $request,bool $storage): Response
    {
        return new Response($this->view->render('admin-users',$this->listing($request,$storage)+['storage'=>$storage]));
    }
    public function maintenancePage(Request $request): Response
    {
        $this->audit->flush();
        return new Response($this->view->render('admin-maintenance',$this->settings->maintenance()));
    }
    public function updateRole(Request $request):Response
    {
        $target=$request->body['user_id']??null;$enabled=$request->body['admin_flag']??null;$expected=$request->body['expected_admin_flag']??null;$version=$request->body['version']??null;
        if(!$request->isApi()){
            foreach([$target,$version] as $value)if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,15}$/D',$value))throw new HttpException(422,'INVALID_INPUT');
            foreach([$enabled,$expected] as $value)if(!in_array($value,['0','1'],true))throw new HttpException(422,'INVALID_INPUT');
            $target=(int)$target;$version=(int)$version;$enabled=$enabled==='1';$expected=$expected==='1';
        }
        if(!is_int($target)||$target<1||$target>9007199254740990||!is_int($version)||$version<1||$version>=9007199254740990||!is_bool($enabled)||!is_bool($expected))throw new HttpException(422,'INVALID_INPUT');
        $result=$this->roles->update($target,$enabled,$expected,$version,(int)$this->auth->requireUser()['id']);$this->audit->flush();
        return $request->isApi()?Response::json($result):Response::redirect('/admin/users');
    }
    public function maintenance(Request $request): Response { return Response::json($this->settings->maintenance()); }
    public function updateMaintenance(Request $request): Response
    {
        $enabled=$request->body['enabled'] ?? null;
        $version=$request->body['version'] ?? null;
        $api=str_starts_with($request->path,'/api/');
        if ($api) {
            if (!is_bool($enabled) || !is_int($version) || $version<1 || $version>PHP_INT_MAX-1) throw new HttpException(422,'INVALID_INPUT');
        } else {
            if (!in_array($enabled,['0','1'],true) || !is_string($version) || !preg_match('/^[1-9][0-9]{0,17}$/D',$version)) throw new HttpException(422,'INVALID_INPUT');
            $enabled=$enabled==='1'; $version=(int)$version;
        }
        $result=$this->settings->setMaintenance($enabled,$version,(int)$this->auth->requireUser()['id']);
        $this->audit->flush();
        unset($result['audit_context']);
        return $api ? Response::json($result) : Response::redirect('/admin/maintenance');
    }
}
