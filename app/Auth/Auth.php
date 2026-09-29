<?php
declare(strict_types=1);

namespace App\Auth;

use App\Config;
use App\Http\HttpException;
use App\Repositories\AuthRepository;

final class Auth
{
    private const COOKIE = 'search_remember';
    private ?array $user = null;

    public function __construct(private readonly Config $config, private readonly AuthRepository $repository) {}

    public function restore(): void
    {
        $cookie = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($cookie) || !preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/D',$cookie,$match)) {
            unset($_SESSION['user_id'],$_SESSION['device_id']);
            return;
        }
        $row = $this->repository->authenticate($match[1],hash('sha256',$match[2]),time());
        if (!$row) { $this->clear(); return; }
        if (($_SESSION['device_id'] ?? '') !== $match[1] || (int)($_SESSION['user_id'] ?? 0) !== (int)$row['user_id']) {
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
        }
        $_SESSION['device_id'] = $match[1];
        $_SESSION['user_id'] = (int)$row['user_id'];
        $this->user = $this->repository->user((int)$row['user_id']);
        $this->cookie($cookie,time()+90*86400);
    }

    public function login(array $identity, string $agent): void
    {
        $userId = $this->repository->upsertIdentity($identity,$_SESSION['locale'] ?? 'en');
        if (isset($_SESSION['user_id'],$_SESSION['device_id'])) {
            $this->repository->revoke((int)$_SESSION['user_id'],$_SESSION['device_id']);
        }
        $device = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $this->repository->createDevice($userId,$device,hash('sha256',$token),DeviceAgent::parse($agent),time());
        session_regenerate_id(true);
        unset($_SESSION['csrf'],$_SESSION['oauth_state']);
        $_SESSION['user_id']=$userId;
        $_SESSION['device_id']=$device;
        $this->user=$this->repository->user($userId);
        $this->cookie($device.'.'.$token,time()+90*86400);
    }

    public function user(): ?array { return $this->user; }

    public function requireUser(): array
    {
        return $this->user ?? throw new HttpException(401,'AUTH_REQUIRED');
    }

    public function logout(): void
    {
        if ($this->user) { $this->repository->revoke((int)$this->user['id'],$_SESSION['device_id']); }
        $this->clear();
    }

    private function clear(): void
    {
        $locale=$_SESSION['locale'] ?? 'en';
        $_SESSION=['locale'=>$locale,'created_at'=>time()];
        session_regenerate_id(true);
        $this->user=null;
        $this->cookie('',time()-3600);
    }

    private function cookie(string $value,int $expires): void
    {
        setcookie(self::COOKIE,$value,[
            'expires'=>$expires,'path'=>'/','secure'=>$this->config->get('session.secure',true),
            'httponly'=>true,'samesite'=>'Lax',
        ]);
    }
}
