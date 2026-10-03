<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Session;
use App\Config;
use App\Database\Database;
use App\Helpers\View;
use App\Http\{HttpException, Request, Response};

final class CoreController
{
    public function __construct(private readonly Config $config, private readonly View $view)
    {
    }

    public function home(Request $request): Response
    {
        return $this->config->get('installed')
            ? new Response($this->view->render('home', [
                'site_name' => $this->config->get('site.name'),
                'providers' => require dirname(__DIR__, 2) . '/config/providers.php',
            ]))
            : Response::redirect('/installer');
    }

    public function health(Request $request): Response
    {
        if (!$this->config->get('installed')) {
            throw new HttpException(503, 'NOT_INSTALLED');
        }
        try {
            Database::connect($this->config->get('database'))->query('SELECT 1');
        } catch (\Throwable) {
            throw new HttpException(503, 'DATABASE_UNAVAILABLE');
        }
        return Response::json(['status' => 'ok']);
    }
    public function privacy(Request $request):Response
    {
        return new Response($this->view->render('privacy'));
    }

    public function csrf(Request $request): Response
    {
        return Response::json(['csrf_token' => Session::csrf()]);
    }

    public function locale(Request $request): Response
    {
        $locale = $request->input('locale');
        if (!in_array($locale, ['ja', 'en'], true)) {
            throw new HttpException(422, 'INVALID_INPUT');
        }
        $_SESSION['locale'] = $locale;
        return Response::redirect($this->config->get('installed') ? '/' : '/installer');
    }
}
