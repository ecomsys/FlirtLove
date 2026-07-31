<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            
            // === СВЯЗИ ===
            // Ссылка на чат. Если чат удаляется, сообщения тоже летят в мусорку (cascade).
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            
            // Кто отправил. nullable, потому что система может слать служебные сообщения 
            // (например, "Вам мэтч!" или "Пользователь заблокировал вас"). 
            // Без cascade! Если отправитель удалит аккаунт, переписка должна остаться у получателя.
            $table->foreignId('sender_id')->nullable()->constrained('users'); 
            
            // === КОНТЕНТ СООБЩЕНИЯ ===
            // Тип: text, image (фото, пока скрыто на фронте), system (служебное), gift (подарок)
            $table->enum('type', ['text', 'image', 'system', 'gift'])->default('text');
            
            // Текст сообщения (или текст-пожелание к подарку). 
            // Для image и gift может быть пустым.
            $table->text('body')->nullable();
            
            // Ссылка на вложение (картинка юзера ИЛИ URL подарка из каталога)
            $table->string('attachment_url')->nullable();
            
            // Если это подарок (type=gift), ссылка на ID подарка в таблице gifts.
            // nullOnDelete, чтобы если админ удалит подарок из каталога, сообщение не упало, а просто осталось без картинки.
            $table->foreignId('gift_id')->nullable()->constrained('gifts')->nullOnDelete(); 

            // === МОДЕРАЦИЯ ВЛОЖЕНИЙ (Фоток в чате) ===
            // Текстовые сообщения по умолчанию approved (чтобы чат не тормозил). 
            // Если type=image, в сервис-классе мы принудительно ставим 'pending' и отдаем на проверку ИИ/админу.
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
            $table->string('reject_reason')->nullable(); // porn, scam, minor, ad
            $table->foreignId('moderated_by')->nullable()->constrained('users'); // Какой модератор заблокировал фотку
            $table->timestamp('moderated_at')->nullable();
            
            $table->timestamps();
            
            // МЯГКОЕ УДАЛЕНИЕ КРИТИЧЕСКИ ВАЖНО! 
            // Юзер жмет "Удалить сообщение у себя", мы ставим deleted_at. 
            // Для службы безопасности и МВД сообщение остается в БД навсегда.
            $table->softDeletes();

            // === ИНДЕКСЫ ===

            // 1. Для пагинации переписки (самый важный индекс!). 
            // Запрос: WHERE chat_id = ? ORDER BY created_at DESC
            $table->index(['chat_id', 'created_at']);
            
            // 2. Для поиска всех сообщений конкретного юзера (если нужно посмотреть, что он вообще шлет)
            $table->index('sender_id');
            
            // 3. Для очереди модерации в админке: найти все сообщения с картинками, ожидающие проверки
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

// Разбор архитектуры (Полная безопасность):

// Связь с подарками: Обрати внимание на gift_id. Если юзер шлет подарок, он падает сюда со type = 'gift'. 
// В чате это будет выглядеть как красивая карточка подарка + текст. 
// Если админ через год удалит этот подарок из магазина (gifts), сообщение в чате не сломается и не 
// исчезнет (благодаря nullOnDelete), просто картинка станет недоступна.
// Модерация по умолчанию (status = 'approved'): Мы не можем проверять каждое "Привет". 
// Но в коде (в сервис-классе) у тебя будет проверка: 
// if ($message->type === 'image') { $message->status = 'pending'; }. И фотка улетает в очередь.
// Мягкое удаление (softDeletes): Это закон в дейтинге. Если мошенник обманул юзера на деньги в чате, 
// а потом юзер удалил переписку (или мошенник удалил у себя) — для службы безопасности сообщения остаются. 
// Они помечаются как удаленные, но админ видит их в логах.
// Как работает статус rejected? Если модератор или ИИ забраковал фотку, в БД ставится status = 'rejected'. 
// На фронте (в Livewire/JS) у собеседника вместо картинки отобразится плашка "Фотография заблокирована модератором".