<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            // Кэшируем tier, чтобы знать, за что платил юзер, даже если тариф удалят
            $table->enum('tier', ['premium', 'vip']); 
            
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            
            // Логика автопродления (пока не используем, но поля есть)
            $table->boolean('is_auto_renew')->default(false);
            $table->string('provider_subscription_id')->nullable()->index();
            
            $table->string('status')->default('active')->index(); // active, canceled, expired, failed
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status', 'ends_at']);
            $table->index(['status', 'ends_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('user_subscriptions'); }
};