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
        $before = [
            'status' => $photo->getOriginal('status'), 
            'is_primary' => $photo->getOriginal('is_primary')
        ];

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

        $photo->refresh();

        $after = [
            'status' => 'approved', 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'url' => $photo->getOriginal('path_original')
            ]
        ];
        
        AdminLog::record('photo.approve', $photo, $admin, $before, $after, participants: [$photo->user_id]);
        Cache::forget('admin_sidebar_stats');
        

        return $photo;
    }

    /**
     * Отклонить единичное фото (с мягким удалением и карантином файлов).
     */
    public function reject(Photo $photo, User $admin, string $reason = 'other'): void
    {
        $user = $photo->user;
        
        $before = [
            'status' => $photo->getOriginal('status'), 
            'is_primary' => $photo->getOriginal('is_primary')
        ];

        $photo->markAsRejected($admin->id, $reason);
        
        if ($photo->is_primary) {
            $photo->update(['is_primary' => false]);
            
            $nextAvatar = $user?->photos()->approved()->where('id', '!=', $photo->id)->first();
            if ($nextAvatar) {
                $nextAvatar->update(['is_primary' => true]);
            }
        }

        if ($user) {
            $user->notify(new PhotoModerated($photo->id, $photo->user_id, 'rejected', 1));
        }

        $photo->refresh();

        $after = [
            'status' => 'rejected', 
            'reject_reason' => $reason, 
            'is_primary' => $photo->is_primary, 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'url' => $photo->getOriginal('path_original')
            ]
        ];
        
        AdminLog::record('photo.reject', $photo, $admin, $before, $after, participants: [$photo->user_id]);
        Cache::forget('admin_sidebar_stats');
        
    }

    /**
     * Физическое удаление фото (вместе с файлами на диске).
     */
    public function destroy(Photo $photo, User $admin): void
    {
        $userId = $photo->user_id;
        $photoId = $photo->id;
        $photoPath = $photo->getOriginal('path_original'); // Сохраняем путь до удаления
        
        $before = [
            'status' => $photo->getOriginal('status'), 
            'path' => $photoPath
        ];

        $after = [
            'status' => 'destroyed', 
            'deleted_by' => $admin->id, 
            'deleted_at' => now()->toDateTimeString(),
            'context' => [
                'photo_id' => $photoId,
                'user_id' => $userId,
                'url' => $photoPath
            ]
        ];

        // ВАЖНО: Пишем лог ДО физического удаления, чтобы связь не сломалась
        AdminLog::record('photo.destroy', $photo, $admin, $before, $after, participants: [$userId]);

        // Модель Photo удалит файлы через слушатель forceDeleting
        $photo->forceDelete();
    }

        /**
     * Мягкое удаление (перемещение в карантин).
     */
    public function softDelete(Photo $photo, User $admin): void
    {
        $before = [
            'status' => $photo->getOriginal('status'), 
            'deleted_at' => $photo->getOriginal('deleted_at')
        ];

        $photo->delete();

        $after = [
            'status' => 'quarantined', 
            'deleted_at' => now()->toDateTimeString(), 
            'deleted_by' => $admin->id,
            'context' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'url' => $photo->getOriginal('path_original')
            ]
        ];

        AdminLog::record('photo.soft_delete', $photo, $admin, $before, $after, participants: [$photo->user_id]);
    }

    /**
     * Восстановление из карантина (возвращение в очередь на модерацию).
     */
    public function restore(Photo $photo, User $admin): void
    {
        $before = [
            'status' => $photo->getOriginal('status'), 
            'deleted_at' => $photo->getOriginal('deleted_at')
        ];

        DB::Transaction(function () use ($photo) {
            $photo->restore();
            $photo->update([
                'status' => 'pending',
                'reject_reason' => null,
                'moderated_by' => null,
                'moderated_at' => null,
            ]);
        });

        $photo->refresh();

        $after = [
            'status' => 'pending', 
            'restored_at' => now()->toDateTimeString(), 
            'restored_by' => $admin->id,
            'context' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'url' => $photo->getOriginal('path_original')
            ]
        ];

        AdminLog::record('photo.restore', $photo, $admin, $before, $after, participants: [$photo->user_id]);
        Cache::forget('admin_sidebar_stats');
        
    }
   
    /**
     * Сделать фото главным (аватаркой).
     */
    public function setPrimary(Photo $photo, User $admin): void
    {
        $before = [
            'is_primary' => $photo->getOriginal('is_primary'), 
            'status' => $photo->getOriginal('status')
        ];

        DB::transaction(function () use ($photo) {
            Photo::where('user_id', $photo->user_id)->update(['is_primary' => false]);
            $photo->update(['is_primary' => true]);
        });

        $photo->refresh();
        
        $after = [
            'is_primary' => true, 
            'set_by' => $admin->id,
            'context' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'url' => $photo->getOriginal('path_original')
            ]
        ];
        
        AdminLog::record('photo.set_primary', $photo, $admin, $before, $after, participants: [$photo->user_id]);
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

        $after = [
            'status' => 'approved', 
            'count' => $count, 
            'photo_ids' => $photoIds->toArray(), 
            'moderated_by' => $admin->id,
            'context' => [
                'user_id' => $user->id
            ]
        ];
        
        AdminLog::record('photo.mass_approve', $user, $admin, $before, $after, participants: [$user->id]);
        Cache::forget('admin_sidebar_stats');
        

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

        DB::transaction(function () use ($photos, $admin) {
            foreach ($photos as $photo) {
                $this->reject($photo, $admin, 'mass_reject');
            }
        });

        $user->notify(new PhotoModerated($photoIds->first(), $user->id, 'rejected', $count));
        
        $after = [
            'status' => 'rejected', 
            'count' => $count, 
            'photo_ids' => $photoIds->toArray(), 
            'reject_reason' => 'mass_reject',
            'context' => [
                'user_id' => $user->id
            ]
        ];
        
        AdminLog::record('photo.mass_reject', $user, $admin, $before, $after, participants: [$user->id]);
        Cache::forget('admin_sidebar_stats');
        

        return $count;
    }
}