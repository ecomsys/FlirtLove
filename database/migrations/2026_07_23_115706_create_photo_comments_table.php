<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_comments', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('photo_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->text('content');
            
            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])->default('pending');
            
            // Для вложенных комментариев (ответы)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('photo_comments')
                ->onDelete('cascade');
            
            // Статистика (денормализация для скорости)
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('reports_count')->default(0);
            
            // Флаги
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_pinned')->default(false);
            
            // Временные метки модерации
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            $table->timestamps();
            
            // === ИНДЕКСЫ ===
            $table->index(['photo_id', 'status']); // Для вывода комментариев под фото
            $table->index(['user_id', 'created_at']); // История комментариев юзера
            $table->index(['status', 'created_at']); // Для админки (очередь модерации)
            $table->index('parent_id'); // Для быстрого поиска ответов на комментарий
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_comments');
    }
};