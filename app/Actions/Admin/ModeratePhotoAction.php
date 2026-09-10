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
    public function approve(Photo $photo, User $admin, bool $notify = true): Photo
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

        if ($notify && $photo->user) {
            $cacheKey = "photo_approved_notif_{$photo->user_id}";
            if (!Cache::has($cacheKey)) {
                $photo->user->notify(new PhotoModerated($photo->id, $photo->user_id, 'approved', 1));
                Cache::put($cacheKey, true, now()->addMinutes(5));
            }
        }

        $after = [
            'status' => 'approved', 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'url' => $photo->path_original
            ]
        ];
        
        AdminLog::record('photo.approve', $photo, $admin, $before, $after, participants: [$photo->user_id]);
        $this->clearCaches();

        return $photo;
    }

    public function reject(Photo $photo, User $admin, string $reason = 'other', bool $notify = true): void
    {
        $user = $photo->user;
        
        $before = [
            'status' => $photo->getOriginal('status'), 
            'is_primary' => $photo->getOriginal('is_primary')
        ];

        $photo->markAsRejected($admin->id, $reason);
        
        if ($photo->is_primary) {
            $photo->update(['is_primary' => false]);
            
            $nextAvatar = $user?->photos()->approved()->where('id', '!=', $photo->id)->orderByDesc('is_primary')->oldest()->first();
            if ($nextAvatar) {
                $nextAvatar->update(['is_primary' => true]);
            }
        }

        if ($notify && $user) {
            $user->notify(new PhotoModerated($photo->id, $photo->user_id, 'rejected', 1));
        }

        $after = [
            'status' => 'rejected', 
            'reject_reason' => $reason, 
            'is_primary' => $photo->is_primary, 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'url' => $photo->path_original
            ]
        ];
        
        AdminLog::record('photo.reject', $photo, $admin, $before, $after, participants: [$photo->user_id]);
        $this->clearCaches();
    }

    public function destroy(Photo $photo, User $admin): void
    {
        $userId = $photo->user_id;
        $photoId = $photo->id;
        $photoPath = $photo->path_original;
        
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

        AdminLog::record('photo.destroy', $photo, $admin, $before, $after, participants: [$userId]);
        $photo->forceDelete();
        $this->clearCaches();
    }

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
                'url' => $photo->path_original
            ]
        ];

        AdminLog::record('photo.soft_delete', $photo, $admin, $before, $after, participants: [$photo->user_id]);
        $this->clearCaches();
    }

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

        $after = [
            'status' => 'pending', 
            'restored_at' => now()->toDateTimeString(), 
            'restored_by' => $admin->id,
            'context' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'url' => $photo->path_original
            ]
        ];

        AdminLog::record('photo.restore', $photo, $admin, $before, $after, participants: [$photo->user_id]);
        $this->clearCaches();
    }
   
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
        
        $after = [
            'is_primary' => true, 
            'set_by' => $admin->id,
            'context' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'url' => $photo->path_original
            ]
        ];
        
        AdminLog::record('photo.set_primary', $photo, $admin, $before, $after, participants: [$photo->user_id]);
        $this->clearCaches();
    }

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

        // Ограничиваем лог, чтобы не раздувать базу
        $logIds = $photoIds->take(100)->toArray();
        
        $after = [
            'status' => 'approved', 
            'count' => $count, 
            'sample_ids' => $logIds, 
            'moderated_by' => $admin->id,
            'context' => ['user_id' => $user->id]
        ];
        
        AdminLog::record('photo.mass_approve', $user, $admin, $before, $after, participants: [$user->id]);
        $this->clearCaches();

        return $count;
    }

    public function rejectAllForUser(User $user, User $admin): int
    {
        // Берем только нужные поля для логики, чтобы не жрать память
        $photos = $user->photos()->where('status', 'pending')->select(['id', 'is_primary', 'user_id'])->get();
        if ($photos->isEmpty()) return 0;

        $photoIds = $photos->pluck('id');
        $count = $photos->count();
        $before = ['status' => 'pending', 'count' => $count];

        $avatarRejected = $photos->contains(fn($p) => $p->is_primary);

        DB::transaction(function () use ($photoIds, $admin, $user, $avatarRejected) {
            Photo::whereIn('id', $photoIds)->update([
                'status' => 'rejected',
                'moderated_by' => $admin->id,
                'moderated_at' => now(),
                'reject_reason' => 'mass_reject',
                'is_primary' => false,
            ]);

            if ($avatarRejected) {
                $nextAvatar = $user->photos()->approved()->orderByDesc('is_primary')->oldest()->first();
                if ($nextAvatar) {
                    $nextAvatar->update(['is_primary' => true]);
                }
            }
        });

        $user->notify(new PhotoModerated($photoIds->first(), $user->id, 'rejected', $count));
        
        $logIds = $photoIds->take(100)->toArray();
        
        $after = [
            'status' => 'rejected', 
            'count' => $count, 
            'sample_ids' => $logIds, 
            'reject_reason' => 'mass_reject',
            'context' => ['user_id' => $user->id]
        ];
        
        AdminLog::record('photo.mass_reject', $user, $admin, $before, $after, participants: [$user->id]);
        $this->clearCaches();

        return $count;
    }

    private function clearCaches(): void
    {
        Cache::forget('admin_sidebar_stats');
        Cache::forget('admin_photo_counts');
    }
}