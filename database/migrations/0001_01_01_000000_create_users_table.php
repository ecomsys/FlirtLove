<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // === 1. БАЗОВАЯ АВТОРИЗАЦИЯ ===
            // Это ядро, без которого Laravel не сможет работать.
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // Для возможности восстановления аккаунта

            // === 2. СТАТУСЫ И ФЛАГИ ===
            // Почему они здесь, а не в профиле? Потому что мы проверяем их 
            // при КАЖДОМ запросе (в middleware или policies). 
            // Например: "А не забанен ли юзер?", "Может ли он смотреть админку?".
            // Если вынести их в профиль, придется делать JOIN на каждый чих.
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_banned')->default(false);            
            $table->boolean('is_shadowbanned')->default(false);
            $table->boolean('is_premium')->default(false);
            $table->timestamp('premium_expires_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('has_completed_onboarding')->default(false);
            $table->boolean('is_deactivated')->default(false); // Заморозка аккаунта           

            // === 3. АКТИВНОСТЬ И ЛИМИТЫ ===
            // Эти поля часто обновляются. last_seen обновляется при каждом открытии страницы.
            // Если они лежат в таблице users, строка блокируется (row lock) реже, 
            // так как сама таблица более легкая.
            $table->unsignedInteger('superlikes_remaining')->default(5);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamp('last_seen')->nullable();

            // === 4. КРИТИЧЕСКИ ВАЖНЫЕ ИНДЕКСЫ ===
            // В dating-сайтах 90% запросов к БД — это поиск анкет для ленты.
            // Без этих индексов база будет сканировать всю таблицу (Full Table Scan) и ляжет.
            
            // Индекс для ленты рекомендаций: мы всегда фильтруем не забаненных, 
            // прошедших онбординг юзеров определенного пола.
            $table->index(['is_banned', 'has_completed_onboarding']); 
                      
            // Индекс для списка "Кто онлайн". Сортировка по last_seen.
            $table->index('last_seen');
            
            // Индекс для крон-задач (раз в минуту проверяем, у кого истекла подписка).
            $table->index(['is_premium', 'premium_expires_at']);
        });

        // Таблицы стандартного ядра Laravel, оставляем без изменений
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};