<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Console\Scheduling\Schedule;

// ВАЖНО !!! Запускаеться в bootstrap/app.php 

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // контроль за локалью
        $middleware->web(append: [
            SetLocale::class,
            UpdateLastSeen::class,         
        ]);
        
        $middleware->alias([                 
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,    
            'onboarding' => \App\Http\Middleware\EnsureOnboardingCompleted::class,            
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*'),
        );
    })

    // Добавляем сюда все задачи планировщика (php artisan schedule:list)
    ->withSchedule(function (Schedule $schedule) {                
        // Очистка старых комментариев к фоткам каждую ночь в 3:00
        $schedule->command('comments:clean --days=30')->dailyAt('03:00');
        
        // withoutOverlapping(10) - не запускать, если предыдущий запуск еще работает (таймаут 10 мин)
        // onOneServer() - критично для прода, если крон крутится на нескольких серверах
        $schedule->command('broadcasts:send-scheduled')
            ->everyMinute()
            ->withoutOverlapping(10)
            ->onOneServer();

        // Очистка карантина отклоненных фото каждую ночь в 04:00
        $schedule->command('photos:purge-quarantine')->dailyAt('04:00');    
    })   
    ->create();
