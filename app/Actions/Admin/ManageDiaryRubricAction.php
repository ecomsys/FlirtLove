<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Diary;
use App\Models\Rubric;
use App\Models\User;

class ManageDiaryRubricAction
{
    /**
     * Создать рубрику
     */
    public function create(array $data, User $admin): Rubric
    {
        $rubric = Rubric::create($data);
        
        $after = [
            'status' => 'created', 
            'context' => [
                'rubric_id' => $rubric->id,
                'user_id' => $rubric->user_id,
                'name' => $rubric->name,
                'is_system' => is_null($rubric->user_id)
            ]
        ];

        $participants = $rubric->user_id ? [$rubric->user_id] : [];
        
        AdminLog::record('rubric.create', $rubric, $admin, null, $after, participants: $participants);
        
        return $rubric;
    }

    /**
     * Обновить рубрику
     */
    public function update(Rubric $rubric, array $data, User $admin): void
    {
        $before = [
            'name' => $rubric->getOriginal('name'), 
            'is_active' => $rubric->getOriginal('is_active')
        ];
        
        $rubric->update($data);
        $rubric->refresh();
        
        $after = [
            'name' => $rubric->name,
            'is_active' => $rubric->is_active,
            'context' => [
                'rubric_id' => $rubric->id,
                'user_id' => $rubric->user_id,
                'is_system' => is_null($rubric->user_id)
            ]
        ];

        $participants = $rubric->user_id ? [$rubric->user_id] : [];
        
        AdminLog::record('rubric.update', $rubric, $admin, $before, $after, participants: $participants);
    }

    /**
     * Удалить рубрику (с переносом постов или обнулением)
     */
    public function delete(Rubric $rubric, ?int $reassignId, User $admin): void
    {
        $userId = $rubric->user_id;
        $rubricId = $rubric->id;
        $rubricName = $rubric->name;

        $before = [
            'name' => $rubricName, 
            'reassign_to' => $reassignId
        ];

        // Переносим посты в новую рубрику или обнуляем
        Diary::where('rubric_id', $rubricId)->update(['rubric_id' => $reassignId]);

        $after = [
            'status' => 'deleted', 
            'deleted_by' => $admin->id,
            'context' => [
                'rubric_id' => $rubricId,
                'user_id' => $userId,
                'name' => $rubricName,
                'is_system' => is_null($userId)
            ]
        ];

        $participants = $userId ? [$userId] : [];

        // Пишем лог ДО физического удаления
        AdminLog::record('rubric.delete', $rubric, $admin, $before, $after, participants: $participants);
        
        $rubric->delete();
    }

    /**
     * Скрыть/Показать рубрику
     */
    public function toggleStatus(Rubric $rubric, User $admin): void
    {
        $before = ['is_active' => $rubric->getOriginal('is_active')];
        
        $rubric->update(['is_active' => !$rubric->is_active]);
        $rubric->refresh();
        
        $after = [
            'is_active' => $rubric->is_active, 
            'toggled_by' => $admin->id,
            'context' => [
                'rubric_id' => $rubric->id,
                'user_id' => $rubric->user_id,
                'name' => $rubric->name,
                'is_system' => is_null($rubric->user_id)
            ]
        ];

        $participants = $rubric->user_id ? [$rubric->user_id] : [];
        
        // ФИКС: Выделим в отдельный экшен для красивой иконки в таймлайне
        AdminLog::record('rubric.toggle_status', $rubric, $admin, $before, $after, participants: $participants);
    }
}