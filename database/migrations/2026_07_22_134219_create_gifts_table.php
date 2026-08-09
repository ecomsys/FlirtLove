<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            
            // Название подарка (например, "Красная роза", "Крутой Мерседес")
            $table->string('name');
            
            // Слаг для URL или системных идентификаторов (например, 'red_rose')
            $table->string('slug')->unique();
            
            // Путь к картинке/анимации подарка в каталоге
            $table->string('image_url');
            
            // Цена во внутренней валюте (кредитах). 
            // Мы храним кредиты в user_preferences.credits
            $table->unsignedInteger('price');
            
            // Категория для группировки в каталоге (romantic, cars, 18+, male, female)
            $table->string('category')->nullable()->index();
            
            // Флаг активности. Админ может скрыть подарок из продажи, не удаляя его из БД 
            // (чтобы старые отправленные подарки не ломались)
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gifts');
    }
};

// Разбор архитектуры:

// Здесь всё максимально лаконично и правильно.

// price (Цена в кредитах): Заметь, мы используем unsignedInteger, а не decimal. 
// В дейтингах внутренняя валюта (кредиты/монеты) всегда целые числа, чтобы избежать проблем с 
// плавающей запятой (0.1 + 0.2 = 0.30000000000000004).
// is_active: Позволяет админу скрывать подарки на праздники (например, новогодние) или убирать 
// нерентабельные, не нарушая историю отправленных.