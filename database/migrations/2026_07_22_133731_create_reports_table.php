<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            
            // === КТО И НА КОГО ЖАЛУЕТСЯ ===
            // Убрали cascade! Если юзер удаляется, жалоба остается в БД для службы безопасности.
            $table->foreignId('reporter_id')->constrained('users'); // Кто подал жалобу
            $table->foreignId('reported_id')->constrained('users'); // На кого подали жалобу
            
            // === ПОЛИМОРФНАЯ СВЯЗЬ (На что жалоба?) ===
            // Позволяет жаловаться на фото, сообщения, комментарии, анкеты.
            // reportable_type = 'Photo', reportable_id = 123
            $table->nullableMorphs('reportable'); 

            // === СУТЬ ЖАЛОБЫ ===
            // Причина жестко задана строкой (slug) для фильтрации в админке: spam, porn, scam, insult, minor
            $table->string('reason')->index(); 
            // Свободное описание от юзера (что именно нарушил, текст жалобы)
            $table->text('description')->nullable();

            // === СТАТУС И РАЗБИРАТЕЛЬСТВО ===
            // pending (ожидает), resolved (разобрано - нарушитель наказан), rejected (разобрано - нет нарушения)
            $table->string('status')->default('pending')->index();
            
            // Что сделал модератор: ban (бан), warn (предупреждение), shadowban, no_action (нет нарушения)
            $table->string('resolution')->nullable(); 
            // Внутренний комментарий модератора (почему он принял такое решение)
            $table->text('resolution_note')->nullable(); 

            // === КТО РАЗБИРАЛ ===
            // Админ/модератор (приводим к единому неймингу с admin_logs)
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable(); // Когда жалоба была закрыта

            $table->timestamps();
            $table->softDeletes(); // Жалобы не удаляются физически никогда!

            // === ИНДЕКСЫ ===
            // Очередь в админке: выводим все pending жалобы по дате создания
            $table->index(['status', 'created_at']);
            
            // Проверка: жаловался ли уже этот юзер на этого юзера (чтобы не спамили жалобы)
            $table->index(['reporter_id', 'reported_id']);
            
            // Для профиля админки: показать все жалобы, разобранные конкретным модератором
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};