<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule; // <<--- AÑADIR ESTA LÍNEA
use App\Console\Commands\GenerateMonthlyLiquidation; // <<--- AÑADIR ESTA LÍNEA

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) { // <<--- AÑADIR ESTE MÉTODO
        $schedule->command(GenerateMonthlyLiquidation::class)->lastDayOfMonth('23:59');
    })->create();