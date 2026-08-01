<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            
            // === КТО СОЗДАЛ ===
            // Админ, инициировавший рассылку. Без cascade, чтобы история рассылок не удалялась при увольнении админа.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            
            // === ПАРАМЕТРЫ РАССЫЛКИ ===
            // Тип: in_app (в колокольчик/в БД), push (мобильный пуш), email. SMS убрано за ненадобностью.
            $table->enum('type', ['in_app', 'push', 'email'])->default('in_app');
            $table->string('title');
            $table->text('message');
            
            // Payload для фронта (deep link куда переходить при клике, иконки и т.д.)
            $table->json('data')->nullable();
            
            // === АУДИТОРИЯ (Сегментация) ===
            // Критерии выборки из админки. 
            // Например: {"gender": "male", "city": "Moscow", "is_premium": false, "age_min": 25}
            $table->json('target_audience')->nullable(); 
            
            // === СТАТУСЫ И ВРЕМЯ ===
            // draft (черновик), scheduled (запланирована), sending (в процессе), sent (отправлена), failed (ошибка)
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable(); // Когда крон должен запустить
            $table->timestamp('started_at')->nullable(); // Когда начали фактическую рассылку
            $table->timestamp('sent_at')->nullable(); // Когда полностью завершили
            
            // === СТАТИСТИКА (Денормализация для админки) ===
            // Чтобы не делать COUNT по таблице notifications (которая может разрастись до миллионов строк)
            $table->unsignedInteger('total_recipients')->default(0); // Скольким планировалось отправить
            $table->unsignedInteger('sent_count')->default(0); // Сколько успешно ушло
            $table->unsignedInteger('failed_count')->default(0); // Сколько упало с ошибкой
            
            $table->timestamps();

            // === ИНДЕКСЫ ===
            // Для крон-задачи: ищем запланированные рассылки, время которых пришло
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};