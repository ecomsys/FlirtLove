<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            
            // === СВЯЗИ ===
            // Ссылка на чат. Если чат удаляется из БД, то и связи участников летят в мусорку (cascade).
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            
            // Ссылка на юзера. Убрали cascade! Если юзер удаляет аккаунт, мы не должны 
            // удалять чат для второго собеседника (чтобы он мог прочитать старую переписку).
            $table->foreignId('user_id')->constrained('users')->nullable()->nullOnDelete();

            // === СЧЕТЧИКИ И ВРЕМЯ (Денормализация) ===
            // Сколько непрочитанных сообщений в этом чате у конкретно этого юзера.
            // Считать через COUNT(*) WHERE read_at IS NULL при каждом открытии списка чатов — смерть для БД.
            $table->unsignedInteger('unread_count')->default(0);
            
            // Когда юзер последний раз открывал этот чат (чтобы пометить сообщения как прочитанные)
            $table->timestamp('last_read_at')->nullable();

            // === НАСТРОЙКИ КОНКРЕТНОГО ДИАЛОГА ===
            // Юзер нажал "Скрыть чат" (архивировать). Чат пропадает из списка, но не удаляется.
            $table->boolean('is_hidden')->default(false)->index(); 
            // Юзер замьютил чат (отключил пуши от этого собеседника)
            $table->boolean('is_muted')->default(false);
            // Юзер заблокировал собеседника в этом чате (не может писать, но история видна)
            $table->boolean('is_blocked')->default(false);

            $table->timestamps();

            // === ИНДЕКСЫ ===

            // 1. Один юзер может быть участником одного чата только один раз (защита от дубликатов)
            $table->unique(['chat_id', 'user_id']);
            
            // 2. Для вывода списка всех чатов конкретного юзера (экран "Мои диалоги")
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_participants');
    }
};


// Разбор архитектуры:

// Почему unread_count здесь? Это классическая денормализация. Когда собеседник пишет сообщение, 
// воркер делает UPDATE chat_participants SET unread_count = unread_count + 1 WHERE chat_id = X AND user_id != Отправитель. 
// Это работает в сотни раз быстрее, чем высчитывать непрочитанные на лету.

// Флаги is_hidden, is_muted, is_blocked: В современных дейтингах юзер хочет управлять конкретным диалогом. 
// Например, замьютить навязчивого ухажера, не удаляя переписку. 
// Эти флаги относятся не к чату в целом (там два человека), а именно к участию конкретного юзера в этом чате. 
// Поэтому они лежат здесь.
// Безопасность удаления: Я убрал onDelete('cascade') с user_id. Если Вася удалит аккаунт, 
// Маша должна иметь возможность открыть чат с Васей и увидеть его последние 
// сообщения (хотя написать уже не сможет). Если бы стоял каскад, чат бы испарился и у Маши, 
// что вызвало бы баги на фронте.