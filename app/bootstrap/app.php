<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'bossku.api' => \App\Http\Middleware\BosskuApiAuth::class,
            'secure.headers' => \App\Http\Middleware\SecureHeaders::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('bossku:process-learning-events')->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // SSE clients send Accept: text/event-stream; still return JSON errors (not 302 redirects).
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            $accept = (string) $request->header('Accept', '');

            return str_contains($accept, 'text/event-stream')
                || str_contains($accept, 'application/json');
        });
    })->create();
