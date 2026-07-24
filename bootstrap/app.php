<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;

// ВАЖНО !!! Запускаеться в bootstrap/app.php 

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
            'onboarding' => \App\Http\Middleware\EnsureOnboardingCompleted::class,
            'redirect.admin' => \App\Http\Middleware\RedirectIfAdmin::class,   
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*'),
        );
    })
    ->withSchedule(function (Schedule $schedule) {
        // Добавляем сюда все задачи планировщика
        
        // Очистка старых комментариев к фоткам каждую ночь в 3:00
        $schedule->command('comments:clean --days=30')->dailyAt('03:00');
    })   
    ->create();
