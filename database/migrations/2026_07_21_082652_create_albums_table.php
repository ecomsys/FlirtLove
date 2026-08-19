<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            
            // Владелец альбома. Если юзер удаляется, альбомы летят в мусорку (cascade)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Название альбома (по умолчанию "Общие", если юзер не придумал имя)
            $table->string('name')->default('Общие');
            
            // Текстовое описание альбома (необязательное)
            $table->text('description')->nullable();
            
            // Флаг системного альбома (создается при регистрации, юзер не может его удалить)
            $table->boolean('is_default')->default(false);
            
            // Флаг приватного альбома (18+ или доступ только по запросу/VIP)
            $table->boolean('is_private')->default(false);
            
            // Денормализованный счетчик фото. Ускоряет вывод списка альбомов, чтобы не делать COUNT запросов
            $table->unsignedInteger('photos_count')->default(0);
            
            $table->timestamps();

            $table->softDeletes();
            
            // Индекс для быстрого вывода публичных/приватных альбомов конкретного юзера
            $table->index(['user_id', 'is_private']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};