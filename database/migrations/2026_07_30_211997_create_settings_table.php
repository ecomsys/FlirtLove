<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            
            // Уникальный ключ (например, 'general.site_name', 'limits.free_likes')
            $table->string('key')->unique();
            
            // Значение настройки. text, чтобы влезали длинные тексты (правила, договоры)
            $table->text('value')->nullable();
            
            // Группа для вкладок в админке (general, limits, finance, seo)
            $table->string('group')->default('general');
            
            // === ДЛЯ ГЕНЕРАЦИИ ФОРМЫ В АДМИНКЕ ===
            // Человекочитаемое название (например, "Название сайта")
            $table->string('label')->nullable();
            // Подсказка для админа (например, "Сколько лайков доступно бесплатно")
            $table->string('description')->nullable();
            // Тип поля в UI: text, textarea, boolean, integer, select, json
            $table->string('type')->default('text'); 
            // Для типа 'select' храним варианты: {"0": "Нет", "1": "Да"}
            $table->json('options')->nullable(); 
            
            // Флаг, можно ли отдавать это значение на фронтенд (для API)
            $table->boolean('is_public')->default(false);
            
            $table->timestamps();

            // === ИНДЕКСЫ ===
            // Для вывода настроек по группам в админке
            $table->index('group');
            // Для быстрой выборки публичных настроек для API
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};


// Как это будет работать в Livewire (представь картину):

// В админке ты делаешь страницу "Настройки". Ты делаешь запрос Setting::where('group', 'limits')->get().
// Livewire проходится циклом по настройкам:

// php

// @foreach($settings as $setting)
//     @if($setting->type == 'boolean')
//         <x-toggle wire:model="settings.{{ $setting->key }}" :label="$setting->label" />
//     @elseif($setting->type == 'text')
//         <x-input wire:model="settings.{{ $setting->key }}" :label="$setting->label" :hint="$setting->description" />
//     @endif
// @endforeach
// И у тебя автоматически строится форма!