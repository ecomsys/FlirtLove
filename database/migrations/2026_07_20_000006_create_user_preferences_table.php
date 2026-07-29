<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            
            // Связь 1 к 1 с юзером
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');

            // === 1. ЛОКАЛИЗАЦИЯ И ТЕМА ===
            $table->string('locale', 5)->default('ru');
            $table->string('theme', 10)->default('light');

            // === 2. ПРЕДПОЧТЕНИЯ ПОИСКА (Базовые) ===
            // Эти поля могут участвовать в запросах (например, "найти юзеров, которые ищут девушек 18-25"),
            // но обычно они просто подставляются в фильтр текущего юзера. 
            $table->unsignedInteger('preferred_age_min')->default(18);
            $table->unsignedInteger('preferred_age_max')->default(99);
            $table->string('preferred_gender')->default('any');
            $table->unsignedInteger('preferred_distance_km')->default(50);
            
            // Расширенный фильтр поиска (JSON).
            // Сюда мы будем сохранять значения из словарей, которые мы вынесли в user_profiles.
            // Например: {"body_type": 3, "smoking": 8, "education_level": 6}
            // Поиск по этим фильтрам будет происходить так: мы читаем этот JSON, 
            // и подставляем его значения в WHERE запрос к таблице user_profiles.
            $table->json('search_filters')->nullable(); 

            // === 3. ФИЛЬТРЫ ЧАТА (Приватность) ===
            $table->boolean('chat_filter_enabled')->default(false);
            $table->json('chat_filter_settings')->nullable();

            // === 4. ПРИВАТНОСТЬ И ВИДИМОСТЬ ===
            $table->boolean('is_invisible')->default(false); // Сервис Невидимка (VIP)
            $table->boolean('hide_intimate')->default(false); // Скрывать 18+ фото
            $table->boolean('disable_photo_comments')->default(false);
            $table->boolean('hide_from_search')->default(false); // Не показывать в ленте

            // === 5. УВЕДОМЛЕНИЯ ===
            $table->boolean('push_enabled')->default(true); // Глобальный тумблер Push
            $table->json('email_settings')->nullable(); // Настройки Email по категориям

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};