<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_views', function (Blueprint $table) {
            $table->id();
            
            // Кто смотрел
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            
            // Кого смотрели
            $table->foreignId('viewed_id')->constrained('users')->cascadeOnDelete();
            
            $table->timestamps();

            // === ИНДЕКСЫ ===
            
            // 1. Защита от дубликатов. Один юзер смотрит другого -> обновляем только время (updated_at)
            $table->unique(['viewer_id', 'viewed_id']);
            
            // 2. Для вывода списка "Кто смотрел меня" (WHERE viewed_id = ? ORDER BY updated_at DESC)
            $table->index(['viewed_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_views');
    }
};