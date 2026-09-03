<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            
            // === КТО СДЕЛАЛ ===
            // Админ/модератор. nullable, т.к. действия могут совершаться системой (воркером) автоматически.
            // Без cascade! Если админа уволят и удалят его аккаунт, история его действий должна остаться.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            
            // === ЧТО СДЕЛАЛ И НАД ЧЕМ (Полиморфная связь) ===
            // Тип действия (action): user.ban, photo.approve, transaction.refund, setting.update
            $table->string('action')->index();
            
            // Полиморфная связь. Позволяет логировать действия с ЛЮБЫМИ таблицами.
            // Например: loggable_type = 'Photo', loggable_id = 105
            // Или: loggable_type = 'User', loggable_id = 42
            $table->nullableMorphs('loggable'); 

            // === ДОКАЗАТЕЛЬНАЯ БАЗА (ДИФФЫ) ===
            // Состояние объекта ДО изменения. Например: {"status": "active"}
            $table->json('before')->nullable();
            // Состояние объекта ПОСЛЕ изменения. Например: {"status": "banned", "ban_reason": "scam"}
            $table->json('after')->nullable();

            $table->json('participants')->nullable();

            // === ТЕХНИЧЕСКИЕ ДАННЫЕ ===
            // С какого IP админ совершил действие (для расследований взломов аккаунтов админов)
            $table->ipAddress('ip_address')->nullable();
            // User-Agent браузера админа
            $table->string('user_agent')->nullable();

            $table->timestamps();
            
            // ВАЖНО: Никаких softDeletes! Логи нельзя удалять или скрывать.
            
            // === ИНДЕКСЫ ===
            // 1. Для вывода истории действий конкретного админа (кто, что и когда трогал)
            $table->index(['admin_id', 'created_at']);
            
            // 2. Полиморфный индекс создается автоматически через nullableMorphs('loggable'),
            // но для админки часто нужен поиск по типу сущности (например, показать все логи по фото)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
    }
};

// Разбор архитектуры (Big Brother is watching):

// Полиморфность (nullableMorphs): Это магия Laravel. 
// Тебе не нужно создавать отдельные таблицы для логов банов, логов фоток, логов финансов. 
// Все пишется сюда. Если модератор забанил юзера, пишется: 
// action = 'user.ban', loggable_type = 'User', loggable_id = 10. 
// Если саппорт сделал рефанд: 
// action = 'transaction.refund', loggable_type = 'Transaction', loggable_id = 55.
// before и after: Это спасение для "ой, я не туда нажал". 
// Если админ случайно изменил цену тарифа с 500 на 5 рублей, ты открываешь лог, 
// видишь дифф before и в один клик можешь откатить значение обратно. 
// Мы будем заполнять эти поля в Observer-классах Laravel.
// Отсутствие softDeletes: Логи аудита должны быть неизменны. Их нельзя удалить никак, 
// кроме как прямым SQL-запросом в базу (к которому имеет доступ только DevOps).