<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_gifts', function (Blueprint $table) {
            $table->id();
            
            // === КТО И КОМУ ===
            // Отправитель. Без cascade! Если отправитель удалит аккаунт, 
            // получатель должен видеть подарок в своей истории.
            $table->foreignId('sender_id')->constrained('users')->nullable()->nullOnDelete();
            
            // Получатель. Без cascade по той же причине.
            $table->foreignId('receiver_id')->constrained('users')->nullable()->nullOnDelete();
            
            // === СВЯЗЬ С КАТАЛОГОМ И СНЭПШОТ ===
            // Ссылка на сам подарок. nullOnDelete: если админ удалит подарок из каталога, 
            // запись в истории останется, просто потеряет прямую связь с каталогом.
            $table->foreignId('gift_id')->nullable()->constrained('gifts')->nullOnDelete();
            
            // Снапшот данных на момент отправки (КРИТИЧЕСКИ ВАЖНО).
            // Если админ поменяет цену или картинку в каталоге, старые отправленные подарки не изменятся.
            $table->string('snapshot_name'); 
            $table->string('snapshot_image_url'); 
            $table->unsignedInteger('snapshot_price'); // Сколько стоил на момент отправки

            // === ДОП. ИНФА ===
            // Текстовое сообщение, которое отправитель может прикрепить к подарку
            $table->string('message')->nullable(); 
            
            // Приватный подарок (видят только отправитель и получатель, в анкете публично не светится)
            $table->boolean('is_private')->default(false);
            
            // === СТАТУС ПРОЧТЕНИЯ ===
            // Прочитан ли подарок получателем (для счетчика "Новые подарки" в меню)
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
            
            // Мягкое удаление. Если юзер хочет убрать подарок со своей страницы, 
            // он его удаляет только у себя, но в БД (для админки и фин. отчетов) он остается.
            $table->softDeletes();

            // === ИНДЕКСЫ ===
            
            // 1. Для вывода подарков в анкете юзера (только публичные)
            $table->index(['receiver_id', 'is_private']);
            
            // 2. Для вывода истории "Мои подарки" (отправленные/полученные)
            $table->index('sender_id');
            
            // 3. Для счетчика непрочитанных подарков
            $table->index(['receiver_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_gifts');
    }
};

// Разбор архитектуры (Защита денег и истории):

// Снапшот (snapshot_*): Это золотой стандарт финтех-систем и систем с виртуальной валютой. 
// Списание кредитов происходит на основе snapshot_price, а не gift->price. 
// Иначе будет баг: сегодня подарок стоит 100 кредитов, завтра 10, 
// а юзер кричит саппорту: "Где мои 90 кредитов?".
// Приватность (is_private): В дейтингах часто делают так: юзер может отправить "горячий" подарок (18+), 
// но чтобы он не светился всем в профиле, ставится флаг is_private.
// Счетчики (is_read, read_at): Как и в чатах, это денормализация. 
// Чтобы на иконке колокольчика вывести "Вам 2 новых подарка", мы делаем быстрый COUNT 
// по индексу receiver_id + is_read, не сканируя сами тексты.
// Soft Deletes: Позволяет юзеру "убрать" подарок со своей страницы (чтобы не позориться, 
// если ему прислали что-то пошлое), но в админке эта транзакция будет видна, чтобы мы понимали, 
// куда ушли кредиты.