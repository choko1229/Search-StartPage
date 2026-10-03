<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Helpers\View;
use App\Http\{HttpException, Request, Response};
use App\Services\{EnvironmentCheck, FileLogger, InstallationService};

final class InstallerController
{
    public function __construct(
        private readonly InstallationService $installer,
        private readonly EnvironmentCheck $environment,
        private readonly View $view,
        private readonly FileLogger $logger,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($this->installer->locked()) {
            if (!empty($_SESSION['install_complete']) && $request->method === 'GET') {
                return new Response($this->view->render('installer', ['step' => 7]));
            }
            throw new HttpException(409, 'ALREADY_INSTALLED');
        }
        $secure = ($request->server['HTTPS'] ?? '') === 'on';
        $local = \App\Http\Transport::allowsLocalHttp($request->server);
        if (!$secure && !$local) {
            throw new HttpException(403, 'HTTPS_REQUIRED');
        }
        if (($_SESSION['install_expires'] ?? 0) < time()) {
            unset($_SESSION['install_step'], $_SESSION['install_draft']);
        }
        $step = (int) ($_SESSION['install_step'] ?? 1);
        $error = null;
        if ($request->method === 'POST') {
            try {
                $this->advance($request, $step);
                return Response::redirect('/installer');
            } catch (HttpException $exception) {
                \App\Exceptions\ErrorHandler::logCaught($exception);
                $error = $exception->errorCode;
            } catch (\Throwable $exception) {
                if (!\App\Exceptions\ErrorHandler::logCaught($exception)) $this->logger->exception($exception, bin2hex(random_bytes(8)));
                $error = 'INSTALL_FAILED';
            }
        }
        return new Response($this->view->render('installer', [
            'step' => $step, 'checks' => $this->environment->results(), 'error' => $error,
            'draft' => $_SESSION['install_draft'] ?? [],
        ]), $error === null ? 200 : 422);
    }

    private function advance(Request $request, int $step): void
    {
        if ($request->input('_step') !== (string) $step) {
            throw new HttpException(422, 'STALE_STEP');
        }
        if ($step === 1) {
            if (!$this->installer->authorize($request->input('setup_key'))) {
                throw new HttpException(403, 'SETUP_KEY_INVALID');
            }
            if (!$this->environment->ready()) {
                throw new HttpException(422, 'ENVIRONMENT_FAILED');
            }
            session_regenerate_id(true);
        } elseif ($step === 2) {
            $database = [
                'host' => trim($request->input('host')),
                'port' => $request->input('port'),
                'name' => trim($request->input('name')),
                'user' => $request->input('user'),
                'password' => $request->input('password'),
            ];
            Database::connect($database);
            $_SESSION['install_draft']['database'] = $database;
        } elseif ($step === 3) {
            $name = trim($request->input('site_name'));
            if ($name === '' || mb_strlen($name) > 100) {
                throw new HttpException(422, 'INVALID_INPUT');
            }
            $_SESSION['install_draft']['site'] = ['name' => $name, 'url' => $this->installer->validateSite($request->input('site_url'))];
        } elseif ($step === 4) {
            $id = trim($request->input('client_id'));
            $secret = $request->input('client_secret');
            if (($id !== '' || $secret !== '') && (!preg_match('/^\d{17,20}$/D', $id) || strlen($secret) < 16 || strlen($secret) > 256)) {
                throw new HttpException(422, 'INVALID_INPUT');
            }
            $_SESSION['install_draft']['discord'] = ['client_id' => $id, 'client_secret' => $secret];
        } elseif ($step === 5) {
            $id = trim($request->input('admin_id'));
            if (!preg_match('/^\d{17,20}$/D', $id)) {
                throw new HttpException(422, 'INVALID_INPUT');
            }
            $_SESSION['install_draft']['admin'] = $id;
        } elseif ($step === 6) {
            $this->installer->install($_SESSION['install_draft']);
            unset($_SESSION['install_draft']);
            $_SESSION['install_complete'] = true;
            session_regenerate_id(true);
        } else {
            throw new HttpException(409, 'ALREADY_INSTALLED');
        }
        $_SESSION['install_step'] = $step + 1;
        $_SESSION['install_expires'] = time() + 1800;
    }
}
