<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            
            // === СВЯЗИ ===
            // Кто купил. Без cascade! Финансовая история должна жить вечно, 
            // даже если юзер удалит аккаунт (для налоговой и фин. отчетов).
            $table->foreignId('user_id')->constrained('users')->nullable()->nullOnDelete();
            
            // Какой тариф был куплен. nullOnDelete: если админ удалит тариф, история не сломается.
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            
            // ID транзакции, которая оплатила эту подписку. 
            // (Мы создадим таблицу transactions следующей). nullOnDelete, чтобы не было жесткой связи.
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            // === СРОКИ ===
            // Когда подписка начала действовать
            $table->timestamp('starts_at');
            // Когда подписка заканчивается. 
            // (В middleware мы проверяем: if (now() < ends_at) => юзер VIP)
            $table->timestamp('ends_at');
            
            // === АВТОПРОДЛЕНИЕ (Рекуррентка) ===
            // Включено ли автопродление (списание с карты каждый месяц)
            $table->boolean('is_auto_renew')->default(false);
            // ID подписки в платежной системе (Stripe/YooKassa). 
            // Нужен, чтобы мы могли через API отменить автопродление, если юзер попросит.
            $table->string('provider_subscription_id')->nullable()->index();
            
            // === СТАТУСЫ И ОТМЕНЫ ===
            // active (активна), expired (истекла), canceled (отменена юзером/админом), failed (ошибка списания)
            $table->string('status')->default('active')->index();
            
            // Если юзер отключил автопродление, мы пишем дату отмены. 
            // Подписка при этом продолжает работать до ends_at! (Это требование Apple/Google)
            $table->timestamp('canceled_at')->nullable();

            $table->timestamps();
            
            // === ИНДЕКСЫ ===
            
            // 1. Для проверки, есть ли у юзера активная подписка (и для кэширования в users.is_premium)
            $table->index(['user_id', 'status']);
            
            // 2. Для крон-задачи: каждый день искать подписки, которые скоро истекают, чтобы слать пуши "Продлите VIP"
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};

// Разбор архитектуры (Защита от проблем с Apple/Google):

// Разница между status и canceled_at: Это критически важно для мобильных приложений. 
// Если юзер нажимает "Отменить подписку" в App Store, он отменяет будущие списания. 
// Но он уже заплатил за этот месяц! Поэтому мы ставим canceled_at = NOW(), 
// но status оставляем active до тех пор, пока не наступит ends_at. 
// Только после ends_at крон-задача поменяет status на expired.
// provider_subscription_id: Без этого поля ты не сможешь управлять подписками. 
// Если юзер придет в саппорт и скажет: "Уберите мою карту, я больше не хочу платить", 
// ты должен будешь через API ЮKassa/Stripe отправить запрос на отмену, используя этот ID.
// transaction_id: Связь один-к-одному с платежом. Если юзер要求ит возврат (refвозврат), 
// ты сможешь легко найти, к какой подписке привязан этот платеж, и снять VIP-статус.