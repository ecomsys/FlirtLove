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
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // === 1. ЛОКАЛИЗАЦИЯ И ИНТЕРФЕЙС ===
            $table->string('locale', 5)->default('ru');
             $table->string('theme', 10)->nullable();
            $table->boolean('chat_widget_enabled')->default(true); // Плавающий виджет чата
            $table->boolean('chat_sound_enabled')->default(true);  // Звук чата

            // === 2. ПРЕДПОЧТЕНИЯ ПОИСКА (Кого я ищу) ===
            $table->enum('preferred_gender', ['any', 'male', 'female'])->default('any')->index();
            $table->unsignedTinyInteger('preferred_age_min')->default(18);
            $table->unsignedTinyInteger('preferred_age_max')->default(99);
            $table->unsignedSmallInteger('preferred_distance_km')->default(50);
            $table->jsonb('search_filters')->nullable(); // Расширенные фильтры (JSONB для скорости)

            // === 3. ПРИВАТНОСТЬ И ВИДИМОСТЬ (Кто видит меня) ===
            $table->boolean('hide_from_search')->default(false); // Скрыть из общей ленты
            $table->boolean('is_invisible')->default(false); // Режим инкогнито (VIP)
            $table->boolean('hide_intimate')->default(false); 
            $table->boolean('disable_photo_comments')->default(false);
            $table->boolean('show_quick_replies')->default(true); 
            
            // VIP-фильтр: кто меня видит
            $table->enum('visibility_gender', ['any', 'male', 'female'])->default('any')->index();
            $table->unsignedTinyInteger('visibility_age_min')->default(18)->index();
            $table->unsignedTinyInteger('visibility_age_max')->default(99)->index();
            
            // Фильтр входящих сообщений (кто может мне писать)
            $table->boolean('chat_filter_enabled')->default(false);
            $table->jsonb('chat_filter_settings')->nullable();

            // === 4. УВЕДОМЛЕНИЯ И АВТОМАТИЗАЦИЯ ===
            // Базовые тумблеры
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            
            // Автоматизация сайта (важно для анти-спама и функционала дейтинга)
            $table->boolean('push_auto_recommendations')->default(true);  
            $table->boolean('email_auto_recommendations')->default(true); 
            $table->boolean('allow_auto_messages')->default(true);        
            
            // Гранулярные настройки ТОЛЬКО для email (пуши управляются только булевым push_enabled)
            $table->jsonb('email_settings')->nullable(); 

            $table->timestamps();
            
            // === ИНДЕКСЫ ===
            // Для быстрого поиска подходящих юзеров по базовым фильтрам
            $table->index(['preferred_gender', 'preferred_age_min', 'preferred_age_max']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};