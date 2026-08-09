<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            
            // Владелец фото. Если юзер удаляется, фото тоже удаляются (cascade)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Привязка к альбому. nullable, т.к. фото может существовать без альбома (например, при загрузке)
            $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();

            // Тип фото: profile (в анкете) или verification (фото с кодом для проверки личности)
            $table->enum('type', ['profile', 'verification'])->default('profile');
            
            // Пути к файлам. Оригинал обязателен (nullable(false)), 
            // миниатюры нарезаются асинхронно воркером, поэтому могут быть временно null
            $table->string('path_original');
            $table->string('path_large')->nullable();    // w = 1600px
            $table->string('path_medium')->nullable();   // w = 820px
            $table->string('path_thumb')->nullable();    // 200x200

            // Статус модерации: pending (ожидает), approved (одобрено), rejected (отклонено)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            
            // Причина отклонения модератором (porn, minor, stolen, text и т.д.)
            $table->string('reject_reason')->nullable();
            
            // ID админа/модератора, который проверил фото
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Дата и время модерации (для аналитики скорости модерации)
            $table->timestamp('moderated_at')->nullable();

            // Флаг аватарки (главное фото профиля, выводится в ленте рекомендаций)
            $table->boolean('is_primary')->default(false);
            
            // Флаг 18+ фото (может быть в публичном альбоме, но скрывается от юзеров с настройкой hide_intimate)
            $table->boolean('is_intimate')->default(false);
            
            // Порядок сортировки фото внутри альбома/профиля (ручная сортировка юзером)
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
            
            // Мягкое удаление. В дейтинге критически важно: фото не удаляется с диска и из БД физически, 
            // чтобы служба безопасности могла проверить жалобы на удаленные фото
            $table->softDeletes();

            // === ИНДЕКСЫ ===
            
            // Для профиля юзера: показывать только одобренные профильные фото конкретного юзера
            $table->index(['user_id', 'status', 'type']);
            
            // Для быстрого поиска аватарки юзера
            $table->index(['user_id', 'is_primary']);
            
            // Для сортировки фото внутри альбома при просмотре
            $table->index(['album_id', 'position']);
            
            // Для очереди модерации в админке: выбрать профильные фото со статусом pending, отсортированные по дате
            $table->index(['status', 'type', 'created_at']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};