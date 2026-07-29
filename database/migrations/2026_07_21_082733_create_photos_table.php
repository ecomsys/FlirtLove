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
            // Если фото обязательно должно быть в альбоме, убери nullable(). 
            // Но пока оставляем, чтобы фото могло существовать без альбома (например, при загрузке)
            $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();

            // Пути к файлам
            // Убрал дефолтный 'path', чтобы не было путаницы. 
            // path_original - это исходник. Остальные - это уже нарезанные версии.
            $table->string('path_original')->nullable();
            $table->string('path_large')->nullable();    // w = 1600px
            $table->string('path_medium')->nullable();   // w = 820px
            $table->string('path_thumb')->nullable();    // 200x200

            // Статусы и флаги
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_intimate')->default(false);

            // Порядок сортировки внутри альбома/профиля
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            // === ИНДЕКСЫ ===
            
            // Для профиля юзера: показывать только одобренные фото
            $table->index(['user_id', 'status']);
            // Для быстрого поиска аватарки
            $table->index(['user_id', 'is_primary']);
            
            // Для сортировки фото внутри альбома
            $table->index(['album_id', 'position']);
            
            // Для админки: выводить фото по статусам (очередь на модерацию)
            // Мы уже добавили индекс на 'status' выше через ->index() в enum,
            // но для админки часто нужен составной индекс по дате создания:
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};