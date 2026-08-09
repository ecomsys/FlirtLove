<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            
            // Кто подал заявку
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Фото-доказательство (фото с листком). Если фото удалят из БД, заявка останется (nullOnDelete)
            $table->foreignId('photo_id')->nullable()->constrained('photos')->nullOnDelete();
            
            // Статус: pending (ожидает), approved (одобрено), rejected (отклонено)
            $table->string('status')->default('pending')->index();
            
            // Причина отклонения (blurry, fake, no_code)
            $table->string('reject_reason')->nullable();
            
            // Кто из админов проверил
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Когда проверили
            $table->timestamp('moderated_at')->nullable();
            
            $table->timestamps();

            // === ИНДЕКСЫ ===
            
            // Для очереди модерации в админке: выбрать все pending заявки, сортированные по дате
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};