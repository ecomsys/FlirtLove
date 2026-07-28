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
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // Для возможности восстановления аккаунта

            // Локализация и тема
            $table->string('locale', 5)->default('ru');
            $table->string('theme', 10)->default('light');

            // Основная информация
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('dating_goal', ['friends', 'romantic', 'family', 'casual'])->nullable();
            $table->string('city')->nullable();
            $table->text('bio')->nullable();
            $table->text('looking_for')->nullable(); // Кого я хочу найти
            $table->json('interests')->nullable();

            // Внешность и анкета
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('weight')->nullable();
            $table->string('education')->nullable();
            $table->string('occupation')->nullable();
            $table->string('zodiac_sign')->nullable();
            $table->json('profile_details')->nullable(); // Личная информация (словари)

            // Геолокация и адрес
            $table->geography('location', subtype: 'point')->nullable()->index();
            $table->float('latitude', 10, 7)->nullable();
            $table->float('longitude', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();

            // Предпочтения поиска (базовые)
            $table->unsignedInteger('preferred_age_min')->default(18);
            $table->unsignedInteger('preferred_age_max')->default(99);
            $table->string('preferred_gender')->default('any');
            $table->unsignedInteger('preferred_distance_km')->default(50);
            $table->json('search_filters')->nullable(); // Расширенный фильтр поиска (JSON)

            // Фильтры чата (приватность)
            $table->boolean('chat_filter_enabled')->default(false);
            $table->json('chat_filter_settings')->nullable();

            // Статусы и флаги
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->boolean('is_premium')->default(false);
            $table->timestamp('premium_expires_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->unsignedInteger('superlikes_remaining')->default(5);
            $table->boolean('has_completed_onboarding')->default(false);
            $table->boolean('is_deactivated')->default(false); // Заморозка

            // Приватность и Контент
            $table->boolean('is_invisible')->default(false); // Сервис Невидимка
            $table->boolean('hide_intimate')->default(false); // Скрывать 18+ фото
            $table->boolean('disable_photo_comments')->default(false);
            $table->boolean('hide_from_search')->default(false); // Не показывать в ленте

            // Уведомления
            $table->boolean('push_enabled')->default(true); // Глобальный тумблер Push
            $table->json('email_settings')->nullable(); // Настройки Email по категориям

            // Метрики и счетчики
            $table->unsignedInteger('profile_views')->default(0);
            $table->unsignedInteger('likes_count')->default(0);

            // Активность
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamp('last_seen')->nullable();
        });

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