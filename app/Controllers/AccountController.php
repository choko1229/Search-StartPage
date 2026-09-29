<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\{Auth,OAuthState};
use App\Helpers\View;
use App\Http\{Request,Response,HttpException};
use App\Repositories\AuthRepository;
use App\Services\DiscordOAuth;

final class AccountController
{
    public function __construct(private readonly Auth $auth,private readonly AuthRepository $repository,private readonly DiscordOAuth $oauth,private readonly View $view) {}

    public function account(Request $request): Response
    {
        $user=$this->auth->user();
        return new Response($this->view->render('account',[
            'user'=>$user,'oauth_configured'=>$this->oauth->configured(),
            'devices'=>$user?$this->repository->devices((int)$user['id']):[],
            'current_device'=>$_SESSION['device_id'] ?? '',
        ]));
    }

    public function start(Request $request): Response
    {
        $url=$this->oauth->authorizationUrl(OAuthState::issue($_SESSION,time()));
        return new Response('',303,['Location'=>$url,'Referrer-Policy'=>'no-referrer']);
    }

    public function callback(Request $request): Response
    {
        OAuthState::consume($_SESSION,$request->query['state'] ?? null,time());
        $code=$request->query['code'] ?? null;
        if (isset($request->query['error']) || !is_string($code)) { throw new HttpException(400,'OAUTH_FAILED'); }
        $this->auth->login($this->oauth->identify($code),$request->server['HTTP_USER_AGENT'] ?? '');
        return Response::redirect('/account');
    }

    public function logout(Request $request): Response
    {
        $this->auth->requireUser();
        $this->auth->logout();
        return new Response($this->view->render('logged-out'));
    }

    public function device(Request $request): Response
    {
        $user=$this->auth->requireUser();
        $id=$request->input('device_id');
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) { throw new HttpException(422,'INVALID_INPUT'); }
        if ($request->input('action')==='revoke') {
            if ($id===($_SESSION['device_id'] ?? '')) { return $this->logout($request); }
            $this->repository->revoke((int)$user['id'],$id);
        } elseif ($request->input('action')==='rename') {
            $name=trim($request->input('name'));
            if ($name==='' || mb_strlen($name)>100) { throw new HttpException(422,'INVALID_INPUT'); }
            $this->repository->rename((int)$user['id'],$id,$name);
        } else { throw new HttpException(422,'INVALID_INPUT'); }
        return Response::redirect('/account');
    }
}
