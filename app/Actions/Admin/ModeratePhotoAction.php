<?php

namespace App\Actions\Admin;

use App\Jobs\ProcessApprovedPhoto;
use App\Models\AdminLog;
use App\Models\Photo;
use App\Models\User;
use App\Notifications\PhotoModerated;
use Illuminate\Support\Facades\DB;

class ModeratePhotoAction
{
    /**
     * Одобрить единичное фото.
     */
    public function approve(Photo $photo, User $admin): Photo
    {
        // Сохраняем состояние ДО
        $before = $photo->only(['status', 'reject_reason', 'moderated_by', 'moderated_at']);

        // Используем хелпер модели
        $photo->markAsApproved($admin->id);

        // Запускаем нарезку картинок
        ProcessApprovedPhoto::dispatch($photo->id);

        if ($photo->is_primary && $photo->user) {
            $photo->user->update(['is_verified' => true]);
        }

        if ($photo->user) {
            $photo->user->notify(new PhotoModerated($photo->id, $photo->user_id, 'approved', 1));
        }

        // Сохраняем состояние ПОСЛЕ и пишем лог
        $after = $photo->fresh()->only(['status', 'reject_reason', 'moderated_by', 'moderated_at']);
        AdminLog::record('photo.approve', $photo, $admin, $before, $after);

        return $photo;
    }

    /**
     * Отклонить единичное фото (с мягким удалением и карантином файлов).
     */
    public function reject(Photo $photo, User $admin, string $reason = 'other'): void
    {
        $user = $photo->user;
        
        // Сохраняем состояние ДО
        $before = $photo->only(['status', 'reject_reason', 'moderated_by', 'moderated_at', 'deleted_at']);

        // 1. Меняем статус и записываем причину
        $photo->markAsRejected($admin->id, $reason);
        
        // 2. Soft Delete (прячем из БД, но запись остается)
        $photo->delete(); 

        // ФАЙЛЫ НЕ ТРОГАЕМ! Они остаются в карантине на 30 дней.

        if ($user) {
            $user->notify(new PhotoModerated($photo->id, $photo->user_id, 'rejected', 1));
        }

        // Сохраняем состояние ПОСЛЕ (с учетом deleted_at) и пишем лог
        $after = $photo->fresh()->only(['status', 'reject_reason', 'moderated_by', 'moderated_at', 'deleted_at']);
        AdminLog::record('photo.reject', $photo, $admin, $before, $after);
    }
    
    /**
     * Удалить фото из архива навсегда (с физическим удалением файлов).
     */
    public function destroy(Photo $photo, User $admin): void
    {
        $user = $photo->user;

        // Сохраняем данные до уничтожения (после forceDelete они сотрутся)
        $before = $photo->only(['id', 'status', 'deleted_at', 'reject_reason']);

        // forceDelete вызовет событие forceDeleting в модели, которое удалит файлы!
        $photo->forceDelete();

        if ($user) {
            $user->notify(new PhotoModerated($photo->id, $photo->user_id, 'deleted', 1));
        }

        // Пишем лог (after = null, так как объект уничтожен)
        AdminLog::record('photo.destroy', $photo, $admin, $before, null);
    }

    /**
     * Сделать фото главным (аватаркой).
     */
    public function setPrimary(Photo $photo, User $admin): void
    {
        // Сохраняем состояние ДО
        $before = $photo->only(['is_primary']);

        DB::transaction(function () use ($photo) {
            Photo::where('user_id', $photo->user_id)->update(['is_primary' => false]);
            $photo->update(['is_primary' => true]);
        });

        // Сохраняем состояние ПОСЛЕ и пишем лог
        $after = $photo->fresh()->only(['is_primary']);
        AdminLog::record('photo.set_primary', $photo, $admin, $before, $after);
    }

    /**
     * ОДОБРИТЬ ВСЕ фото конкретного юзера разом.
     */
    public function approveAllForUser(User $user, User $admin): int
    {
        $photoIds = $user->photos()->where('status', 'pending')->pluck('id');

        if ($photoIds->isEmpty()) return 0;

        $count = $photoIds->count();
        $before = ['status' => 'pending', 'count' => $count];

        DB::transaction(function () use ($photoIds, $user, $admin) {
            // Обновляем статус и админа для всех фото сразу
            Photo::whereIn('id', $photoIds)->update([
                'status' => 'approved',
                'moderated_by' => $admin->id,
                'moderated_at' => now(),
                'reject_reason' => null,
            ]);

            $hasPrimary = Photo::whereIn('id', $photoIds)->where('is_primary', true)->exists();
            if ($hasPrimary) {
                $user->update(['is_verified' => true]);
            }
        });

        foreach ($photoIds as $id) {
            ProcessApprovedPhoto::dispatch($id);
        }

        $user->notify(new PhotoModerated($photoIds->first(), $user->id, 'approved', $count));

        $after = ['status' => 'approved', 'count' => $count, 'photo_ids' => $photoIds->toArray()];
        
        // Логируем массовое действие, привязывая лог к модели Юзера
        AdminLog::record('photo.mass_approve', $user, $admin, $before, $after);

        return $count;
    }

    /**
     * ОТКЛОНИТЬ ВСЕ фото конкретного юзера разом (с карантином файлов).
     */
    public function rejectAllForUser(User $user, User $admin): int
    {
        $photos = $user->photos()->where('status', 'pending')->get();

        if ($photos->isEmpty()) return 0;

        $photoIds = $photos->pluck('id');
        $count = $photos->count();
        $before = ['status' => 'pending', 'count' => $count];

        DB::transaction(function () use ($photoIds, $admin) {
            Photo::whereIn('id', $photoIds)->update([
                'status' => 'rejected',
                'moderated_by' => $admin->id,
                'moderated_at' => now(),
                'reject_reason' => 'mass_reject',
                'deleted_at' => now(), // Soft delete
            ]);
        });

        $user->notify(new PhotoModerated($photoIds->first(), $user->id, 'rejected', $count));
        
        $after = ['status' => 'rejected', 'count' => $count, 'photo_ids' => $photoIds->toArray()];
        
        // Логируем массовое действие, привязывая лог к модели Юзера
        AdminLog::record('photo.mass_reject', $user, $admin, $before, $after);

        return $count;
    }
}