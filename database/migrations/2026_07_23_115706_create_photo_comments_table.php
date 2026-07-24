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
            
            // К какому фото комментарий
            $table->foreignId('photo_id')
                ->constrained()
                ->onDelete('cascade');
            
            // Кто написал
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            
            // Текст комментария
            $table->text('content');
            
            // Статус модерации
            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])
                ->default('pending');
            
            // Для вложенных комментариев (ответы)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('photo_comments')
                ->onDelete('cascade');
            
            // Статистика
            $table->integer('likes_count')->default(0);
            $table->integer('reports_count')->default(0);
            
            // Флаги
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_pinned')->default(false);
            
            // Временные метки
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            $table->timestamps();
            
            // Индексы для быстрых запросов
            $table->index(['photo_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_comments');
    }
};