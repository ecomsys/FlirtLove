<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('target_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['like', 'dislike', 'superlike'])->default('like');
            $table->timestamps();

            // === ИНДЕКСЫ ===

            // 1. Чтобы юзер не мог свайпнуть одного человека дважды
            $table->unique(['user_id', 'target_user_id']);

            // 2. КРИТИЧЕСКИ ВАЖНЫЙ ИНДЕКС ДЛЯ МАТЧЕЙ!
            // Когда я лайкаю девушку, база ищет: "она меня лайкала?".
            // Запрос: WHERE target_user_id = Я AND user_id = ОНА AND type = 'like'
            // Этот составной индекс закрывает этот запрос за миллисекунды.
            $table->index(['target_user_id', 'user_id', 'type']);
            
            // 3. Для статистики: "Кого я лайкнул?" или "Кто меня лайкнул?"
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swipes');
    }
};