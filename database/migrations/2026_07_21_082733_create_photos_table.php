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

            // Внешние ключи
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('album_id')->nullable()->constrained('albums')->nullOnDelete();

            // Пути к файлам
            $table->string('path')->nullable();
            $table->string('path_original')->nullable();
            $table->string('path_large')->nullable();
            $table->string('path_medium')->nullable();
            $table->string('path_thumb')->nullable();

            // Статусы
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_intimate')->default(false);

            // Порядок
            $table->integer('position')->default(0);

            $table->timestamps();

            // Индексы (все, что были)
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_primary']);
            $table->index(['album_id', 'status']);
            $table->index(['album_id', 'is_primary']);
            $table->index(['album_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};