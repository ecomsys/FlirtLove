<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diaries', function (Blueprint $table) {
            $table->id();
            
            // Автор поста
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Рубрика (если админ удалит рубрику, пост не удалится, рубрика станет NULL)
            $table->foreignId('rubric_id')->nullable()->constrained('rubrics')->nullOnDelete();
            
            // Контент
            $table->string('title');
            $table->longText('body'); // HTML или Markdown
                     
            // Статус: draft, pending (на модерации), published, rejected
            $table->enum('status', ['draft', 'pending', 'published', 'rejected'])->default('draft')->index();
            $table->string('reject_reason')->nullable(); // Причина отклонения модератором
            
            // Дата публикации (для сортировки ленты)
            $table->timestamp('published_at')->nullable();
            
            // Настройки поста
            $table->boolean('is_comments_enabled')->default(true); // Разрешены ли комменты
            
            // Денормализованные счетчики (для скорости)
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes(); // Админ должен видеть удаленные посты

            // === ИНДЕКСЫ ===
            
            // 1. Вывод постов конкретного юзера (только опубликованных)
            $table->index(['user_id', 'status', 'published_at']);
            
            // 2. Вывод постов по рубрике
            $table->index(['rubric_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diaries');
    }
};