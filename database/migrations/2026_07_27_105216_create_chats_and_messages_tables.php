<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица чатов (строго 1 на 1)
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user2_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable()->index(); // Для сортировки списка чатов
            $table->timestamps();

            // Гарантируем, что между двумя юзерами только один чат
            $table->unique(['user1_id', 'user2_id']);
        });

        // Таблица сообщений
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('cascade'); // nullable для системных сообщений от бота
            $table->enum('type', ['text', 'image', 'system'])->default('text'); // system - для пейвола
            $table->text('body');
            $table->timestamps();

            $table->index(['chat_id', 'created_at']); // Оптимизация выборки сообщений чата
        });

        // Счетчик непрочитанных сообщений для каждого юзера
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('chats');
    }
};