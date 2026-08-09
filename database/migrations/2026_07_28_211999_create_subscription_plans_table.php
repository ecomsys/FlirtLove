<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            
            // === БАЗОВАЯ ИНФА ===
            // Название для отображения юзеру (например, "VIP на 1 месяц", "Premium+ 3 месяца")
            $table->string('name');
            // Слаг для внутренних нужд и API (например, 'vip_1_month')
            $table->string('slug')->unique();
            
            // === ФИНАНСЫ ===
            // Цена в реальной валюте (рубли/доллары). Decimal(8,2) - стандарт для денег.
            $table->decimal('price', 8, 2);
            // Код валюты (ISO 4217), по умолчанию рубли
            $table->string('currency', 3)->default('RUB');
            
            // === СРОК ДЕЙСТВИЯ ===
            // Длительность подписки в днях (30, 90, 365). 
            // Если 0 или null (зависит от логики) — можно сделать "бессрочный" VIP, но обычно это дни.
            $table->unsignedSmallInteger('duration_days');
           
            // === ФИЧИ ТАРИФА (Что дает подписка) ===
            // JSON с лимитами и флагами. Когда юзер покупает тариф, middleware читает этот JSON 
            // (или кэширует его в users.is_premium) и дает/забирает доступ.
            // Пример: {"invisible": true, "likes_per_day": 100, "superlikes_per_day": 5, "hide_ads": true}
            $table->json('features')->nullable();
            
            // === ИНТЕГРАЦИЯ С ПЛАТЕЖКАМИ (Apple/Google/Stripe) ===
            // ID продукта в App Store (для iOS)
            $table->string('apple_product_id')->nullable();
            // ID продукта в Google Play (для Android)
            $table->string('google_product_id')->nullable();

            // === СТАТУС И СОРТИРОВКА ===
            // Админ может скрыть тариф из продажи, не удаляя его (чтобы не сломать старые подписки)
            $table->boolean('is_active')->default(true);
            // Порядок вывода на странице "Тарифы" (самый выгодный тариф сверху)
            $table->unsignedSmallInteger('sort_order')->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};


// Разбор архитектуры (Почему так):

// features (JSON): Это супер-фича для админки. Тебе не нужно будет лезть в код, 
// если маркетолог скажет: "Давай дадим VIP-юзерам 100 лайков вместо 50". Админ зайдет в таблицу, 
// поменяет цифру в JSON, и middleware сразу применит новые правила.
// Apple/Google Product IDs: Если ты будешь делать мобильное приложение (а дейтинг 90% трафика — это мобилки), 
// тебе придется заводить продукты в App Store Connect и Google Play Console. 
// Эти поля свяжут твою БД с базами Эппла и Гугла, чтобы сервер понимал, 
// за какой продукт пришла оплата через Webhook.
// trial_days: Важная штука для триалов (например, "3 дня бесплатно, потом 999 руб/мес"). 
// Платежки (Stripe, ЮKassa) требуют указывать триал-период при создании рекуррентного платежа.
// Денежный тип (decimal): Никогда не используй float для денег! Только decimal(8,2). 
// Float теряет точность на дробях, в финансах это приведет к копеечным (а иногда и рублевым) 
// расхождениям в отчетах.