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
            
            // Фото, к которому оставлен комментарий. 
            // Убрали cascade, т.к. используем softDeletes у фото. Если фото удалят насовсем, cascade сработает.
            $table->foreignId('photo_id')->constrained();
            
            // Автор комментария. Аналогично, без cascade, чтобы сохранить комментарии забаненных юзеров для истории.
            $table->foreignId('user_id')->constrained();
            
            // Текст комментария
            $table->text('content');
            
            // Статус модерации: pending (ожидает), approved (одобрено), rejected (отклонено), spam (спам)
            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])->default('pending');
            
            // Причина отклонения модератором (mat, insult, spam и т.д.)
            $table->string('reject_reason')->nullable();
            
            // ID админа/модератора, проверившего комментарий (приводим к единому паттерну с фото)
            $table->foreignId('moderated_by')->nullable()->constrained('users');
            // Дата и время модерации (заменили approved_at и rejected_at на одно поле)
            $table->timestamp('moderated_at')->nullable();
            
            // Для вложенных комментариев (ответы). Ссылается на эту же таблицу.
            $table->foreignId('parent_id')->nullable()->constrained('photo_comments');
            
            // Денормализация для скорости (чтобы не делать COUNT запросы при выводе дерева комментариев)
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('reports_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0); // Добавили счетчик ответов
            
            // Флаги UI
            $table->boolean('is_pinned')->default(false); // Закрепленный комментарий (владельцем фото или админом)
            $table->timestamp('edited_at')->nullable(); // Если не null — комментарий был отредактирован (убрали is_edited за ненадобностью)
            
            $table->timestamps();
            
            // Мягкое удаление. Критически важно для СБ! Если юзер удалил свой мат, он должен остаться в БД.
            $table->softDeletes();
            
            // === ИНДЕКСЫ ===
            
            // Основной запрос: вывести одобренные комментарии верхнего уровня (parent_id = null) для фото
            $table->index(['photo_id', 'status', 'parent_id']);
            
            // История комментариев юзера (для админки и профиля)
            $table->index(['user_id', 'created_at']);
            
            // Для очереди модерации в админке
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_comments');
    }
};