<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Http\{Request,Response};
use App\Helpers\View;
use App\Repositories\LogRepository;
use App\Services\{LogFilters,LogRetention,AdminAuditLogger,ApplicationLogger};

final class AdminLogsController
{
    public function __construct(private readonly LogRepository $repository,private readonly LogRetention $retention,private readonly AdminAuditLogger $audit,private readonly View $view,private readonly ApplicationLogger $application) {}

    public function handle(Request $request,bool $auditOnly=false): Response
    {
        $filters=LogFilters::parse($request->query,$auditOnly);
        $this->retention->run();
        $this->audit->flush();
        $this->application->recover();
        $data=$this->repository->listing($filters);
        return $request->isApi() ? Response::json($data) : new Response($this->view->render('admin-logs',$data+['audit_only'=>$auditOnly]));
    }
}
