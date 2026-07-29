<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица чатов
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['private', 'support'])->default('private');
            $table->foreignId('user1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user2_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
          
            // Уникальность: 1 private и 1 support чат между юзерами
            $table->unique(['user1_id', 'user2_id', 'type'], 'chats_user1_user2_type_unique');
            
            // ИНДЕКС: Для сортировки списка чатов по последнему сообщению
            $table->index('last_message_at');
            
            // ИНДЕКС: Для админки (фильтрация чатов поддержки)
            $table->index('type');
        });

        // Таблица сообщений
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('cascade'); 
            $table->enum('type', ['text', 'image', 'system'])->default('text');
            $table->text('body');
            $table->timestamps();

            // ИНДЕКС: Для пагинации переписки (самый важный индекс!)
            $table->index(['chat_id', 'created_at']);
            
            // ИНДЕКС: Для поиска всех сообщений юзера (или системных)
            $table->index('sender_id');
        });

        // Счетчик непрочитанных сообщений
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_id', 'user_id']);
            
            // ИНДЕКС: Для вывода списка чатов конкретного юзера
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('chats');
    }
};