<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            // Пол
            $table->enum('gender', ['male', 'female'])->nullable()->after('name');
            // День рождения
            $table->date('birth_date')->nullable()->after('gender');
            // Цель знакомства
            $table->enum('dating_goal', ['friends', 'romantic', 'family', 'casual'])->nullable()->after('birth_date');
            // Город
            $table->string('city')->nullable()->after('dating_goal');

            // Время последнего захода (time)
            $table->timestamp('last_login_at')->nullable()->after('has_completed_onboarding');
            //   Последний IP адресс юзера (ip)
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            // Забанен ли пользователь (boolean)
            $table->boolean('is_banned')->default(false)->after('last_login_ip');

            // Запонимаем локаль (По умолчанию будет ru)
            $table->string('locale', 5)->default('ru')->after('email');
            // Запонимаем тему (По умолчанию будет light)
            $table->string('theme', 10)->default('light')->after('locale');
            // Прошел ли пользователь процедуру онбординга (загрузка фото при регистрации = boolean)
            $table->boolean('has_completed_onboarding')->default(false);
            //   Являеться ли пользователь Администратором (boolean)
            $table->boolean('is_admin')->default(false)->after('has_completed_onboarding');
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
