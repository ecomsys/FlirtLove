<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user2_id')->constrained('users')->onDelete('cascade');
            
            // Убрал matched_at, так как created_at выполняет ту же функцию
            $table->timestamps();

            // === ИНДЕКСЫ ===

            // 1. Чтобы не было дубликатов (A-B и B-A)
            $table->unique(['user1_id', 'user2_id']);

            // 2. КРИТИЧЕСКИ ВАЖНО: Для быстрого поиска "Мои матчи"
            // Запрос: WHERE user1_id = ? OR user2_id = ?
            // Без этих двух индексов база будет тормозить при выводе списка матчей.
            $table->index('user1_id');
            $table->index('user2_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_matches');
    }
};