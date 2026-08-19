<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            
            // SEO-слаг
            $table->string('slug')->unique();
            
            // === СВЯЗИ ===
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();

            // === КОНТЕНТ ===
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');

            // === НАВИГАЦИЯ И СТАТУС ===
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            
            // Статус: draft (черновик), published (опубликовано), archived (в архиве)
            $table->string('status')->default('draft')->index();
            
            // Флаг "Закрепленная статья"
            $table->boolean('is_featured')->default(false);

            // === ДЕНОРМАЛИЗАЦИЯ ===
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // === ИНДЕКСЫ ===
            // Для паблик-части: выводить опубликованные статьи по дате создания
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};