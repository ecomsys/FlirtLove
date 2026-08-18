<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Diary;
use App\Models\User;
use App\Notifications\DiaryModerated;
use Illuminate\Support\Facades\Log;

class ModerateDiaryAction
{
    /**
     * Одобрить пост (опционально принимаем мета-настройки: рубрику и комменты)
     */
    public function approve(Diary $diary, User $admin, array $metaData = []): void
    {
        $oldStatus = $diary->status;
        $before = $diary->getOriginal();

        $diary->update(array_merge($metaData, [
            'status' => 'published',
            'published_at' => !$diary->published_at ? now() : $diary->published_at,
            'reject_reason' => null,
        ]));

        AdminLog::record('diary.approve', $diary, $admin, $before, $diary->fresh()->toArray());

        if ($oldStatus !== 'published' && $diary->user) {
            $diary->user->notify(new DiaryModerated($diary, 'approved'));
        }
    }

    /**
     * Отклонить пост
     */
    public function reject(Diary $diary, User $admin, string $reason, array $metaData = []): void
    {
        $oldStatus = $diary->status;
        $before = $diary->getOriginal();

        $diary->update(array_merge($metaData, [
            'status' => 'rejected',
            'reject_reason' => $reason,
        ]));

        AdminLog::record('diary.reject', $diary, $admin, $before, $diary->fresh()->toArray());

        if ($oldStatus !== 'rejected' && $diary->user) {
            $diary->user->notify(new DiaryModerated($diary, 'rejected', $reason));
        }
    }

        /**
     * Снять с публикации (вернуть на модерацию)
     */
    public function unpublish(Diary $diary, User $admin, array $metaData = []): void
    {
        $oldStatus = $diary->status;
        $before = $diary->getOriginal();

        $diary->update(array_merge($metaData, [
            'status' => 'pending',
        ]));

        AdminLog::record('diary.unpublish', $diary, $admin, $before, $diary->fresh()->toArray());

        // ФИКС: Уведомляем юзера, что его пост сняли с публикации
        if ($oldStatus === 'published' && $diary->user) {
            $diary->user->notify(new DiaryModerated($diary, 'unpublished'));
        }
    }

    /**
     * Отправить в карантин (Soft Delete)
     */
    public function delete(Diary $diary, User $admin): void
    {
        $before = $diary->getOriginal();
        $diary->delete();
        AdminLog::record('diary.delete', $diary, $admin, $before, null);
    }

    /**
     * Восстановить из карантина
     */
    public function restore(Diary $diary, User $admin): void
    {
        $before = $diary->getOriginal();
        $diary->restore();
        
        $diary->update([
            'status' => 'pending',
            'reject_reason' => null
        ]);
        
        AdminLog::record('diary.restore', $diary, $admin, $before, $diary->fresh()->toArray());
    }

    /**
     * Удалить навсегда (Force Delete)
     */
    public function forceDelete(Diary $diary, User $admin): void
    {
        $before = $diary->getOriginal();
        AdminLog::record('diary.force_delete', $diary, $admin, $before, null);
        $diary->forceDelete();
    }
}