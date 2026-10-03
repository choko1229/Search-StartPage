<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Http\{Request,Response};
use App\Services\StatisticsInput;
use App\Repositories\StatisticsRepository;
final class StatisticsController {
    public function __construct(private readonly StatisticsRepository $repository){}
    public function event(Request $request):Response {
        $events=StatisticsInput::events($request->jsonObject);$this->repository->record($events);
        return Response::json(['accepted'=>array_column($events,'event_id')]);
    }
}
