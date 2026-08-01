<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            
            // URL адрес страницы (например: privacy-policy, terms-of-service)
            $table->string('slug')->unique();
            
            // SEO и контент
            $table->string('title'); // Заголовок (H1 и Title)
            $table->longText('body')->nullable(); // HTML контент из WYSIWYG редактора (Trix, TinyMCE)
            $table->text('meta_description')->nullable(); // Для SEO
            
            // Управление
            $table->boolean('is_active')->default(true); // Черновик или опубликовано
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};