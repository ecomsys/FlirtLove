<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            
            // === СВЯЗИ ===
            // Кто платил. Без cascade! Финансовая история не должна уничтожаться при удалении аккаунта.
            $table->foreignId('user_id')->constrained('users');
            
            // === ДЕНЬГИ ===
            // Сумма списания в реальной валюте. Decimal(8,2) — железобетонный стандарт для денег.
            $table->decimal('amount', 8, 2);
            // Код валюты (RUB, USD, EUR)
            $table->string('currency', 3)->default('RUB');
            
            // === ТИП И СТАТУС ===
            // Тип операции: subscription (подписка), credits (покупка внутренней валюты), refund (возврат)
            $table->enum('type', ['subscription', 'credits', 'refund'])->default('subscription');
            // Статус: pending (ожидает), success (успешно), failed (ошибка), refunded (возврат)
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending')->index();
            
            // === ИНТЕГРАЦИЯ С ПЛАТЕЖКАМИ (Эквайринг) ===
            // Кто провел платеж: stripe, yookassa, cloudpayments, apple, google, manual (ручной ввод админом)
            $table->string('provider')->nullable();
            // Уникальный ID транзакции от платежки. Нужен для связи с админ-панелями банков и проверки вебхуков.
            $table->string('provider_transaction_id')->nullable()->index();
            
            // === ДОП. ИНФА ===
            // Если это покупка валюты — сколько кредитов начислено
            $table->unsignedInteger('credits_amount')->nullable();
            // Сырые данные от платежки (JSON). Сюда воркер будет складывать весь payload от webhook'а.
            // Если банк спросит "за что заплатил юзер?", у тебя будут все чеки.
            $table->json('meta')->nullable();
            
            $table->timestamps();

            // ВАЖНО: Никаких softDeletes! Финансовые записи нельзя скрывать или удалять. 
            // Если операция отменена, мы меняем status на 'failed' или 'refunded'.

            // === ИНДЕКСЫ ===
            // 1. Для вывода истории платежей юзера в админке (только успешные)
            $table->index(['user_id', 'status', 'created_at']);
            
            // 2. Для фин. дашборда: общая выручка за период
            $table->index(['status', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};


// Разбор архитектуры (Финтех-стандарты):

// Отсутствие softDeletes(): В финансах не бывает "корзины". Если транзакция провалилась, 
// она остается в БД со статусом failed. Если юзер потребовал возврат (чарджбэк в банке), 
// мы ищем эту транзакцию и меняем статус на refunded, а в meta пишем причину. 
// Удалять ничего нельзя — иначе твоя бухгалтерия разъедется с бухгалтерией банка.
// provider_transaction_id: Это спасение для саппорта. Юзер пишет: "С меня списали 500 рублей, 
// а VIP не дали!". Ты открываешь админку, видишь транзакцию со статусом failed. 
// Берешь provider_transaction_id, идешь в личный кабинет ЮKassa/Stripe, вбиваешь его и видишь, 
// что банк отклонил платеж (например, не прошел 3-D Secure). Проблема решена за 10 секунд.
// type = 'refund' и type = 'credits': Дейтинг monetizes не только через подписки. 
// Юзер может докупить 100 кредитов, чтобы отправить 10 подарков. И он может потребовать возврат за эти кредиты, если его забанили. Эта структура позволит тебе разделить выручку на потоки (подписки vs микротранзакции).
// Финансовый блок закрыт на 100%! База готова к приему реальных денег.