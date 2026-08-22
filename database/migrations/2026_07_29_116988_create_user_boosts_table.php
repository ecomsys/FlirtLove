<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // ФИКС: Убрали boost_plan_id (таблицы планов бустов больше нет)
            // Оставляем transaction_id, если буст когда-то будет покупаться напрямую за рубли (как опция)
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            
            // НОВОЕ: Фото, которое будет показываться в ленте во время буста
            $table->foreignId('photo_id')->nullable()->constrained('photos')->nullOnDelete();

            // Кэшируем тип (string удобнее enum'а, если завтра добавим новый буст)
            $table->string('type')->default('profile_boost')->index();
            
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default('active')->index(); // active, expired
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('user_boosts'); }
};