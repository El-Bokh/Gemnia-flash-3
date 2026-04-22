<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnforcePlatformMaintenance;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'permission' => CheckPermission::class,
            'platform.maintenance' => EnforcePlatformMaintenance::class,
        ]);

        $middleware->statefulApi();

        // CSRF-exempt routes (external webhook callers can't send tokens).
        $middleware->validateCsrfTokens(except: [
            'webhook/gumroad',
        ]);

        // Global API rate limiting
        $middleware->throttleApi('120,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
