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

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('photo_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('reason');
            $table->enum('status', ['pending', 'resolved', 'rejected'])->default('pending');
            $table->enum('type', ['user', 'photo'])->default('user');

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Индексы
            $table->index(['user_id', 'status']);
            $table->index(['reported_user_id', 'status']);
            $table->index(['photo_id', 'status']);
            $table->index('moderator_id');
            $table->index(['status', 'created_at']); // Для вывода очереди жалоб в админке
            $table->index('type'); // Для фильтрации: показать только жалобы на фото
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};