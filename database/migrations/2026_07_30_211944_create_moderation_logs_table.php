<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users'); // Кто выполнил
            $table->foreignId('user_id')->constrained('users');  // Над кем выполнили
            $table->string('action'); // 'photo_deleted', 'comment_deleted', 'user_banned', 'user_shadowbanned'
            $table->string('subject_type')->nullable(); // 'Photo', 'Comment'
            $table->unsignedBigInteger('subject_id')->nullable(); // ID удаленного фото/коммента
            $table->json('metadata')->nullable(); // Сюда запишем старый путь к файлу, статус или причину
            $table->text('reason')->nullable(); // Причина (пока опционально)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};