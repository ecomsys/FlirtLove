<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            
            // tier: premium (база) или vip (приоритет в выдаче)
            $table->enum('tier', ['premium', 'vip'])->index();
            $table->string('name'); // "Premium на 1 месяц", "VIP на неделю"
            $table->string('slug')->unique();
            
            $table->decimal('price', 8, 2);
            $table->decimal('old_price', 8, 2)->nullable(); // Для маркетинга (перечеркнутая цена)
            $table->string('currency', 3)->default('RUB');
            $table->unsignedSmallInteger('duration_days'); // Срок в днях
            
            // Поля "на вырост" для рекуррентных платежей Apple/Google
            $table->string('apple_product_id')->nullable();
            $table->string('google_product_id')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('subscription_plans'); }
};