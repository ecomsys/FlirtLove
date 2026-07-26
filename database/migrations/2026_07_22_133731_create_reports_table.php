<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Кто отправил жалобу
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // На кого пожаловались
            $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade');

            // На какое фото (опционально)
            $table->foreignId('photo_id')->nullable()->constrained()->onDelete('cascade');

            // Модератор, обработавший жалобу (добавлено из второй миграции)
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();

            // Причина и статусы
            $table->text('reason');
            $table->enum('status', ['pending', 'resolved', 'rejected'])->default('pending');
            $table->enum('type', ['user', 'photo'])->default('user');

            // Время обработки (из второй миграции)
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            // Мягкое удаление (из второй миграции)
            $table->softDeletes();

            // Индексы для ускорения запросов (добавлены для оптимизации)
            $table->index(['user_id', 'status']);
            $table->index(['reported_user_id', 'status']);
            $table->index(['photo_id', 'status']);
            $table->index('moderator_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};