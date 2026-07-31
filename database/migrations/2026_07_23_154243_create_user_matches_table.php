<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_matches', function (Blueprint $table) {
            $table->id();
            
            // === ГЛАВНОЕ ПРАВИЛО МЭТЧЕЙ ===
            // user1_id ВСЕГДА должен быть меньше user2_id (по ID в базе).
            // Вася (ID 10) лайкнул Машу (ID 5). В БД пишем: user1_id = 5 (Маша), user2_id = 10 (Вася).
            // Зачем? Чтобы не было дубликатов (запись 5-10 и 10-5). 
            // Это правило мы будем жестко enforced в коде (в сервис-классе MatchService).
            $table->foreignId('user1_id')->constrained('users');
            $table->foreignId('user2_id')->constrained('users');
            
            // === СТАТУС МЭТЧЕЙ (Разрыв связи) ===
            // active (мэтч активен, можно писать), unmatched (кто-то нажал "Разматчить")
            $table->enum('status', ['active', 'unmatched'])->default('active');
            
            // Кто инициировал разрыв мэтча (для аналитики и саппорта, если кто-то пожалуется)
            $table->foreignId('unmatched_by')->nullable()->constrained('users');
            $table->timestamp('unmatched_at')->nullable();

            $table->timestamps();

            // === ИНДЕКСЫ ===

            // 1. Защита от дубликатов с учетом правила (user1 < user2).
            // Это гарантирует, что пара юзеров будет иметь только одну запись о мэтче на всю историю.
            $table->unique(['user1_id', 'user2_id']);

            // 2. КРИТИЧЕСКИ ВАЖНО: Для быстрого поиска "Мои мэтчи"
            // Когда юзер открывает экран "Мэтчи", запрос выглядит так:
            // SELECT * FROM user_matches WHERE (user1_id = ? OR user2_id = ?) AND status = 'active'
            // Без этих двух индексов база будет сканировать всю таблицу (Full Table Scan).
            $table->index('user1_id');
            $table->index('user2_id');
            
            // 3. Для быстрой фильтрации только активных мэтчей
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_matches');
    }
};



// Главные фишки этой структуры:

// Правило user1_id < user2_id: Это золотой стандарт дейтинга (Tinder, Badoo). Если его не соблюдать, 
// тебе придется писать страшные запросы с OR для проверки дубликатов, 
// а уникальный индекс unique не сможет защитить от записи 10-5 и 5-10. В коде это будет выглядеть так:

// php

// $user1 = min($myId, $targetId);
// $user2 = max($myId, $targetId);
// UserMatch::create(['user1_id' => $user1, 'user2_id' => $user2]);


// Разрыв мэтча (Unmatch): Мы не удаляем запись из БД, когда юзеры разрывают мэтч. 
// Мы ставим status = 'unmatched'. Это нужно для того, чтобы:
// Выводить историю в админке (они сматчились 1 января, разорвали 5 января).
// Предотвращать повторные мэтчи (если они разорвали мэтч, они не должны снова появиться друг у друга в ленте).
// Нет Soft Deletes: Я не добавил softDeletes сюда. Почему? 
// Потому что поле status = 'unmatched' полностью закрывает потребность. 
// Мы не прячем мэтч, мы явно меняем его состояние. Это удобнее для запросов и аналитики.