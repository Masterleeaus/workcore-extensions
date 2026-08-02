<?php

use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\RequireActiveCompany;
use App\Http\Middleware\UpdateUserLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->append(UpdateUserLastSeen::class);
        $middleware->alias([
            'admin' => IsAdmin::class,
            'company.active' => RequireActiveCompany::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Host-level exception mapping remains intentionally minimal.
    })->create();
