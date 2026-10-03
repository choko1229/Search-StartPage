<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Helpers\View;
use App\Http\{Request,Response};
use App\Repositories\AdminSettingsRepository;

final class Maintenance
{
    public function __construct(private readonly AdminSettingsRepository $settings, private readonly \Closure $resolveAdmin, private readonly View $view,private readonly \App\Services\MaintenanceState $signal,private readonly \App\Helpers\Translator $translator) {}

    public function __invoke(Request $request,callable $next): Response
    {
        $enabled=$this->signal->synchronized(function():bool {
            $enabled=$this->settings->maintenance()['enabled'];
            $this->signal->publish($enabled);
            return $enabled;
        });
        if (!$enabled) return $next($request);
        [$auth,$repository]=($this->resolveAdmin)();
        $user=$auth->user();
        if ($user && $repository->isAdministrator((int)$user['id'])) return $next($request);
        $headers=['Retry-After'=>'60'];
        if (str_starts_with($request->path,'/api/')) {
            $response=Response::error('MAINTENANCE',$this->translator->get('maintenance_message'),503);
            return new Response($response->body,503,$response->headers+$headers);
        }
        return new Response($this->view->render('maintenance',['maintenance_only'=>true]),503,$headers);
    }
}
