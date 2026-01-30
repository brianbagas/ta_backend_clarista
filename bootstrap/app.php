<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Illuminate\Console\Scheduling\Schedule;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'abilitys' => CheckForAnyAbility::class,
            'role' => \App\Http\Middleware\CheckRoleOwner::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // DAFTARKAN COMMAND ANDA DI SINI
        $schedule->command('booking:handle-expired')->everyFiveMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
