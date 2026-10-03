<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Http\{Request,Response};
use App\Helpers\View;
use App\Services\StatisticsPeriod;
use App\Repositories\StatisticsReportRepository;

final class AdminStatisticsController
{
    public function __construct(private readonly StatisticsReportRepository $repository,private readonly View $view){}
    public function read(Request $request): Response
    {
        $data=$this->repository->report(StatisticsPeriod::parse($request->query));
        return $request->isApi()?Response::json($data):new Response($this->view->render('admin-statistics',$data));
    }
}
