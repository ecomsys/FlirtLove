<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_comments', function (Blueprint $table) {
            $table->id();
            
            // К какому посту привязан комментарий
            $table->foreignId('diary_id')->constrained()->cascadeOnDelete();
            
            // Автор комментария (nullable для сохранения истории удаленных юзеров)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Текст комментария
            $table->text('content');
            
            // Для ответов (цитирований). Ссылается на эту же таблицу.
            $table->foreignId('parent_id')->nullable()->constrained('diary_comments')->nullOnDelete();
            
            // Статус модерации (в дейтинге премодерация текста часто не нужна, 
            // но мы оставим структуру, чтобы можно было жаловаться и прятать мат)
            $table->enum('status', ['approved', 'pending', 'rejected', 'spam'])->default('approved');
            $table->string('reject_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            
            // Денормализация для скорости
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes(); // Для СБ!
            
            // === ИНДЕКСЫ ===
            // Для вывода комментариев под постом
            $table->index(['diary_id', 'status', 'parent_id']);
            // Для истории юзера
            $table->index(['user_id', 'created_at']);
            // Для очереди модерации в админке
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_comments');
    }
};