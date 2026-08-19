<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // === 1. БАЗОВАЯ АВТОРИЗАЦИЯ ===
            $table->string('name')->nullable(); 
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
             $table->string('phone', 20)->nullable()->unique(); // Верификация по SMS
            $table->timestamp('phone_verified_at')->nullable(); 
            $table->string('password')->nullable(); // nullable для соцсетей
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // Для восстановления аккаунта

            // === 2. РОЛЬ И СТАТУС ===
            $table->string('role')->default('user')->index(); // user, admin, moderator, support
            $table->string('status')->default('active')->index(); // active, banned, shadowbanned, deactivated
            $table->string('ban_reason')->nullable();
            $table->timestamp('banned_until')->nullable();

            // === 3. ПОДПИСКА (Кэш для middleware) ===
            $table->boolean('is_premium')->default(false);
            $table->timestamp('premium_expires_at')->nullable();

            // === 4. ВЕРИФИКАЦИЯ И ОНБОРДИНГ ===
            $table->boolean('is_verified')->default(false);
            $table->boolean('has_completed_onboarding')->default(false);

            // === 5. АКТИВНОСТЬ И АНТИФРОД ===
            $table->timestamp('last_seen')->nullable()->index(); 
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->string('device_id')->nullable()->index(); 
            $table->string('device_os')->nullable()->index();

            // === КРИТИЧЕСКИ ВАЖНЫЕ ИНДЕКСЫ ===
            $table->index(['status', 'has_completed_onboarding']); 
            $table->index(['is_premium', 'premium_expires_at']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};