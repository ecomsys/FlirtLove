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
        AdminLog::record('rubric.create', $rubric, $admin);
        return $rubric;
    }

    /**
     * Обновить рубрику
     */
    public function update(Rubric $rubric, array $data, User $admin): void
    {
        $before = $rubric->getOriginal();
        $rubric->update($data);
        AdminLog::record('rubric.update', $rubric, $admin, $before, $rubric->fresh()->toArray());
    }

    /**
     * Удалить рубрику (с переносом постов или обнулением)
     */
    public function delete(Rubric $rubric, ?int $reassignId, User $admin): void
    {
        // Переносим посты в новую рубрику или обнуляем
        Diary::where('rubric_id', $rubric->id)->update(['rubric_id' => $reassignId]);

        AdminLog::record('rubric.delete', $rubric, $admin);
        $rubric->delete();
    }

    /**
     * Скрыть/Показать рубрику
     */
    public function toggleStatus(Rubric $rubric, User $admin): void
    {
        $before = $rubric->getOriginal();
        $rubric->update(['is_active' => !$rubric->is_active]);
        AdminLog::record('rubric.update', $rubric, $admin, $before, $rubric->fresh()->toArray());
    }
}