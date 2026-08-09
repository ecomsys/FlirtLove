<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            
            // Категория (collection): 'gifts', 'blog', 'banners'. 
            // Помогает фильтровать в админке.
            $table->string('collection')->default('default')->index();
            
            // Оригинальное имя файла (для отображения в админке)
            $table->string('file_name');
            
            // Путь в filesystem (storage/app/public/media/...)
            $table->string('disk_path');
            
            // Полный URL для фронтенда (кэшируем, чтобы не генерировать каждый раз)
            $table->string('url');
            
            // Тип файла: image, video, document
            $table->string('type')->default('image');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable(); // Размер в байтах
            
            // Кто загрузил (nullable, т.к. мог загрузить система/воркер)
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};