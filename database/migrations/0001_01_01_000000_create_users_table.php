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
            $table->string('slug')->nullable()->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 20)->nullable()->unique(); 
            $table->timestamp('phone_verified_at')->nullable(); 
            $table->string('password')->nullable(); // nullable для соцсетей
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // Для восстановления аккаунта

            // === 2. РОЛЬ И СТАТУС ===
            // Используем enum для экономии места и скорости индексов на миллионных объемах
            $table->enum('role', ['user', 'admin', 'moderator', 'support'])->default('user')->index(); 
            $table->enum('status', ['active', 'banned', 'shadowbanned', 'deactivated'])->default('active')->index(); 
            $table->string('ban_reason')->nullable();
            $table->timestamp('banned_until')->nullable();

            // === 3. ПОДПИСКИ (Независимые: Premium и VIP) ===
            // Убрали boolean флаги. Считаем активной, если дата > now(). 
            // Это убирает баги с рассинхроном крона и базы.
            $table->timestamp('premium_expires_at')->nullable()->index();
            $table->timestamp('vip_expires_at')->nullable()->index();

            // === 4. ВЕРИФИКАЦИЯ И ОНБОРДИНГ ===
            $table->boolean('is_verified')->default(false);
            $table->boolean('has_completed_onboarding')->default(false);

            // === 5. АКТИВНОСТЬ И АНТИФРОД ===
            $table->timestamp('last_seen')->nullable()->index(); 
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            
            // Убрали device_id и device_os. 
            // Инфу о девайсах берем из таблицы sessions или user_auth_logs.

            // === КРИТИЧЕСКИ ВАЖНЫЕ ИНДЕКСЫ ===
            $table->index(['status', 'has_completed_onboarding']); 
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