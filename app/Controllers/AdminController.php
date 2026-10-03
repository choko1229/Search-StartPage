<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Helpers\View;
use App\Http\{Request,Response};
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
}
