<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Helpers\View;
use App\Http\{Request,Response,HttpException};
use App\Repositories\AdminRepository;
use App\Services\BackgroundCompression;

final class AdminController
{
    public function __construct(private readonly AdminRepository $repository, private readonly View $view) {}

    private function overview(): array
    {
        return ['counts'=>$this->repository->dashboard(), 'compression'=>BackgroundCompression::capabilities()];
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
        return $this->repository->users(trim($search),$numbers['page'],$numbers['page_size'],$storage);
    }
    public function users(Request $request): Response { return Response::json($this->listing($request,false)); }
    public function storage(Request $request): Response { return Response::json($this->listing($request,true)); }
    public function usersPage(Request $request): Response { return $this->listPage($request,false); }
    public function storagePage(Request $request): Response { return $this->listPage($request,true); }
    private function listPage(Request $request,bool $storage): Response
    {
        return new Response($this->view->render('admin-users',$this->listing($request,$storage)+['storage'=>$storage]));
    }
}
