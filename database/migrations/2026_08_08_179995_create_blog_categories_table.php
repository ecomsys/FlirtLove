<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            
            // Название для отображения (в админке и на сайте)
            $table->string('name');
            
            // Слаг для URL (например: /blog/category/tips)
            $table->string('slug')->unique();
            
            // Флаг активности (скрытые категории не выводятся на сайте)
            $table->boolean('is_active')->default(true);
            
            // Порядок сортировки (ручной порядок в меню)
            $table->unsignedSmallInteger('sort_order')->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};