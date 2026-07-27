<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Включен ли фильтр (Доступен всем / Настроить фильтр)
            $table->boolean('chat_filter_enabled')->default(false)->after('preferred_distance_km');
            
            // JSON поле для настроек фильтра (пол, возраст от/до, город)
            $table->json('chat_filter_settings')->nullable()->after('chat_filter_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['chat_filter_enabled', 'chat_filter_settings']);
        });
    }
};