<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\UpdateLastSeen;
use App\Http\Middleware\LoadUserRelations;
use Illuminate\Console\Scheduling\Schedule;

// ВАЖНО !!! Запускаеться в bootstrap/app.php 

return Application::configure(basePath: dirname(__DIR__))
     ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // контроль за локалью
        $middleware->web(append: [
            SetLocale::class,
            UpdateLastSeen::class,      
            LoadUserRelations::class,   
            
            \App\Http\Middleware\MakeViteUrlsRelative::class,
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
         // withoutOverlapping(10) - не запускать, если предыдущий запуск еще работает (таймаут 10 мин)
        // onOneServer() - критично для прода, если крон крутится на нескольких серверах
        $schedule->command('broadcasts:send-scheduled')
            ->everyMinute()
            ->withoutOverlapping(10)
            ->onOneServer();

        // Запускаем каждый час
        $schedule->command('subscriptions:process')->hourly()->withoutOverlapping();
        
        // КАРАНТИННЫЕ СЛУЖБЫ    
        $schedule->command('diaries:purge-rejected')->dailyAt('03:30');

        $schedule->command('diary-comments:purge-quarantine')->dailyAt('03:40');
        

        // Отправка в карантин старых отклоненных и спам комментариев к фоткам каждую ночь в 3:00        
        $schedule->command('comments:purge-quarantine --days=30')->dailyAt('03:50');      

        // Очистка карантина отклоненных фото каждую ночь в 04:00
        $schedule->command('photos:purge-quarantine')->dailyAt('04:00');    
        // $schedule->command('photos:purge-quarantine')->everyMinute()->withoutOverlapping();

        // Очистка отклоненных записей дневников
        $schedule->command('diaries:purge-rejected')->dailyAt('04:30');
         
        // Очистка архива жалоб 
        $schedule->command('reports:purge-quarantine --days=30')->dailyAt('05:00');

        
        // Рассылка рекомендаций каждый день в 14:00 (днем)
        $schedule->command('users:send-recommendations')->dailyAt('14:00');
        
    })   
    ->create();
