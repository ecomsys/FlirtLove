<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geoip_locations', function (Blueprint $table) {
            $table->id();
            
            // Иерархия: null = Страна, ID = Регион/Город
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('geoip_locations')
                ->cascadeOnDelete(); // Если удаляем страну, удаляются и её регионы
            
            $table->enum('type', ['country', 'region', 'city'])->index();
            $table->string('name');
            
            // ISO код (например, 'RU', 'US', 'NG'). Нужен для быстрого матчинга с GeoIP
            $table->string('iso_code', 10)->nullable()->index(); 
            
            // Флаги блокировок
            $table->boolean('is_registration_blocked')->default(false)->index();
            $table->boolean('is_feed_blocked')->default(false)->index();

            $table->timestamps();
            
            // Защита от дублей (например, два региона с одинаковым именем в одной стране)
            $table->unique(['parent_id', 'type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geoip_locations');
    }
};