<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            
            // === ТИП ЧАТА ===
            // private (личный чат между юзерами), support (чат с техподдержкой)
            $table->enum('type', ['private', 'support'])->default('private');
            
            // === КЭШ ПОСЛЕДНЕГО СООБЩЕНИЯ (Денормализация) ===
            // Чтобы вывести список чатов юзера (сортировка по последнему сообщению),
            // нам пришлось бы делать JOIN с таблицей messages и искать MAX(created_at).
            // Это убьет базу на тысячах чатов. Поэтому мы храним время последнего сообщения тут.
            // Это поле будет обновляться триггером или обсервером при каждом новом сообщении.
            $table->timestamp('last_message_at')->nullable();

            //  Блокировка чата админом
            $table->boolean('is_locked')->default(false)->index();
            
            $table->timestamps();

            // === ИНДЕКСЫ ===
            
            // 1. Для админки: фильтрация чатов (например, показать только чаты с саппортом)
            $table->index('type');
            
            // 2. Для сортировки списка чатов у юзера (самый свежий диалог всегда сверху)
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};

// Разбор архитектуры:

// Чистота таблицы: Заметь, здесь нет ни user_id, ни message_id. Это просто "коробка". 
// Если завтра ты захочешь сделать групповые чаты (по интересам) — тебе не придется менять структуру 
// этой таблицы, просто добавишь тип group и больше участников в chat_participants.
// Поле last_message_at: Это классическая денормализация для скорости. Когда юзер открывает список диалогов, 
// запрос летит мгновенно: SELECT * FROM chat_participants WHERE user_id = 1 ORDER BY last_message_at DESC. 
// А превью самого текста сообщения мы будем доставать легким подзапросом или хранить прямо в 
// participants (но это уже тонкая настройка кэширования).
// Индексы: Покрывают два главных сценария — вывод списка (по дате) и фильтрация в админке (по типу).