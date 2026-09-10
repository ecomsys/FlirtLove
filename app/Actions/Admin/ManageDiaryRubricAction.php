<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Diary;
use App\Models\DiaryRubric;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ManageDiaryRubricAction
{
    /**
     * Создать рубрику
     */
    public function create(array $data, User $admin): DiaryRubric
    {
        $rubric = DiaryRubric::create($data);
        
        $after = [
            'status' => 'created', 
            'context' => [
                'diary_rubric_id' => $rubric->id,
                'user_id' => $rubric->user_id,
                'name' => $rubric->name,
                'is_system' => is_null($rubric->user_id)
            ]
        ];

        $participants = $rubric->user_id ? [$rubric->user_id] : [];
        
        AdminLog::record('diary_rubric.create', $rubric, $admin, null, $after, participants: $participants);
        
        return $rubric;
    }

    /**
     * Обновить рубрику
     */
    public function update(DiaryRubric $rubric, array $data, User $admin): void
    {
        $before = [
            'name' => $rubric->getOriginal('name'), 
            'is_active' => $rubric->getOriginal('is_active')
        ];
        
        $rubric->update($data);
        // ФИКС: Убрали $rubric->refresh(), update() уже обновил атрибуты в памяти
        
        $after = [
            'name' => $rubric->name,
            'is_active' => $rubric->is_active,
            'context' => [
                'diary_rubric_id' => $rubric->id,
                'user_id' => $rubric->user_id,
                'is_system' => is_null($rubric->user_id)
            ]
        ];

        $participants = $rubric->user_id ? [$rubric->user_id] : [];
        
        AdminLog::record('diary_rubric.update', $rubric, $admin, $before, $after, participants: $participants);
    }

    /**
     * Удалить рубрику (с переносом постов или обнулением)
     */
    public function delete(DiaryRubric $rubric, ?int $reassignId, User $admin): void
    {
        $userId = $rubric->user_id;
        $rubricId = $rubric->id;
        $rubricName = $rubric->name;

        $before = [
            'name' => $rubricName, 
            'reassign_to' => $reassignId
        ];

        // ФИКС: Обернули в транзакцию, чтобы перенос постов и удаление рубрики прошли атомарно
        DB::transaction(function () use ($rubric, $rubricId, $reassignId) {
            Diary::where('diary_rubric_id', $rubricId)->update(['diary_rubric_id' => $reassignId]);
            $rubric->delete();
        });

        $after = [
            'status' => 'deleted', 
            'deleted_by' => $admin->id,
            'context' => [
                'diary_rubric_id' => $rubricId,
                'user_id' => $userId,
                'name' => $rubricName,
                'is_system' => is_null($userId)
            ]
        ];

        $participants = $userId ? [$userId] : [];

        AdminLog::record('diary_rubric.delete', $rubric, $admin, $before, $after, participants: $participants);
        
        // ФИКС: Сбрасываем кэш счетчиков дневников, так как посты переехали в другую рубрику (или стали "Без рубрики")
        Cache::forget('admin_diary_counts');
    }

    /**
     * Скрыть/Показать рубрику
     */
    public function toggleStatus(DiaryRubric $rubric, User $admin): void
    {
        $before = ['is_active' => $rubric->getOriginal('is_active')];
        
        $rubric->update(['is_active' => !$rubric->is_active]);
        // ФИКС: Убрали $rubric->refresh()
        
        $after = [
            'is_active' => $rubric->is_active, 
            'toggled_by' => $admin->id,
            'context' => [
                'diary_rubric_id' => $rubric->id,
                'user_id' => $rubric->user_id,
                'name' => $rubric->name,
                'is_system' => is_null($rubric->user_id)
            ]
        ];

        $participants = $rubric->user_id ? [$rubric->user_id] : [];
        
        AdminLog::record('diary_rubric.toggle_status', $rubric, $admin, $before, $after, participants: $participants);
    }
}