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

            // === 1. ЛОКАЛИЗАЦИЯ И ТЕМА ===
            $table->string('locale', 5)->default('ru');
            $table->string('theme', 10)->default('light');

            // === 2. ПРЕДПОЧТЕНИЯ ПОИСКА (Базовые) ===
            $table->unsignedInteger('preferred_age_min')->default(18);
            $table->unsignedInteger('preferred_age_max')->default(99);
            $table->string('preferred_gender')->default('any')->index();
            $table->unsignedInteger('preferred_distance_km')->default(50);
            $table->json('search_filters')->nullable(); // Расширенные фильтры

            // === 3. ФИЛЬТРЫ ЧАТА (Приватность) ===
            $table->boolean('chat_filter_enabled')->default(false);
            $table->json('chat_filter_settings')->nullable();

            // === 4. ПРИВАТНОСТЬ И ВИДИМОСТЬ ===
            $table->boolean('is_invisible')->default(false); // VIP "Невидимка"
            $table->boolean('hide_intimate')->default(false); // Скрывать 18+ фото
            $table->boolean('disable_photo_comments')->default(false);
            $table->boolean('hide_from_search')->default(false); // Экстренное скрытие

            // === 5. УВЕДОМЛЕНИЯ ===
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->json('email_settings')->nullable(); // Гранулярные настройки

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};