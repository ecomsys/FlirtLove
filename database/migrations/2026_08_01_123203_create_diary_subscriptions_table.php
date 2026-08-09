<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_subscriptions', function (Blueprint $table) {
            $table->id();
            
            // Кто подписался (читатель)
            $table->foreignId('subscriber_id')->constrained('users')->cascadeOnDelete();
            
            // На кого подписался (автор дневника)
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            
            $table->timestamps();

            // === ИНДЕКСЫ ===
            
            // 1. Защита от дублей (Иван не может подписаться на Машу дважды)
            $table->unique(['subscriber_id', 'author_id']);
            
            // 2. Для вывода списка "На кого я подписан" (WHERE subscriber_id = ?)
            $table->index('subscriber_id');
            
            // 3. Для вывода списка "Мои подписчики" (WHERE author_id = ?)
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_subscriptions');
    }
};