<?php

namespace App\Actions\Admin;

use App\Jobs\ProcessApprovedPhoto;
use App\Models\AdminLog;
use App\Models\Photo;
use App\Models\User;
use App\Notifications\PhotoModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ModeratePhotoAction
{
    /**
     * Одобрить единичное фото.
     */
    public function approve(Photo $photo, User $admin): Photo
    {
        // Сохраняем состояние ДО (с указанием ID фото)
        $before = ['photo_id' => $photo->id, 'status' => $photo->status];

        $photo->markAsApproved($admin->id);
        ProcessApprovedPhoto::dispatch($photo->id);

        if ($photo->is_primary && $photo->user) {
            $photo->user->update(['is_verified' => true]);
        }

        if ($photo->user) {
            $cacheKey = "photo_approved_notif_{$photo->user_id}";
            if (!Cache::has($cacheKey)) {
                $photo->user->notify(new PhotoModerated($photo->id, $photo->user_id, 'approved', 1));
                Cache::put($cacheKey, true, now()->addMinutes(5));
            }
        }

        // Сохраняем состояние ПОСЛЕ
        $after = ['photo_id' => $photo->id, 'status' => 'approved'];
        
        // ЛОГИРУЕМ ЮЗЕРА, а не фото! (Ссылка на юзера никогда не сломается)
        AdminLog::record('photo.approve', $photo->user, $admin, $before, $after);

        return $photo;
    }

    /**
     * Отклонить единичное фото (с мягким удалением и карантином файлов).
     */
    public function reject(Photo $photo, User $admin, string $reason = 'other'): void
    {
        $user = $photo->user;
        
        $before = ['photo_id' => $photo->id, 'status' => $photo->status];

        $photo->markAsRejected($admin->id, $reason);
       
        if ($user) {
            $user->notify(new PhotoModerated($photo->id, $photo->user_id, 'rejected', 1));
        }

        $after = ['photo_id' => $photo->id, 'status' => 'rejected', 'reason' => $reason];
        
        // ЛОГИРУЕМ ЮЗЕРА
        AdminLog::record('photo.reject', $user, $admin, $before, $after);
    }

    /**
     * Физическое удаление фото (вместе с файлами на диске).
     */
    public function destroy(Photo $photo, User $admin): void
    {
        $before = ['photo_id' => $photo->id, 'status' => $photo->status];

        // Модель Photo удалит файлы через слушатель forceDeleting
        $photo->forceDelete();

        AdminLog::record('photo.destroy', $photo->user, $admin, $before, null);
    }
   
    /**
     * Сделать фото главным (аватаркой).
     */
    public function setPrimary(Photo $photo, User $admin): void
    {
        $before = ['photo_id' => $photo->id, 'is_primary' => $photo->is_primary];

        DB::transaction(function () use ($photo) {
            Photo::where('user_id', $photo->user_id)->update(['is_primary' => false]);
            $photo->update(['is_primary' => true]);
        });

        $after = ['photo_id' => $photo->id, 'is_primary' => true];
        
        // ЛОГИРУЕМ ЮЗЕРА
        AdminLog::record('photo.set_primary', $photo->user, $admin, $before, $after);
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