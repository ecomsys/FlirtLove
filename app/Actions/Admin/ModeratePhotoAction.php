<?php

namespace App\Actions\Admin;

use App\Jobs\ProcessApprovedPhoto;
use App\Models\Photo;
use App\Models\User;
use App\Notifications\PhotoModerated;
use Illuminate\Support\Facades\DB;

class ModeratePhotoAction
{
    /**
     * Одобрить единичное фото.
     */
    public function approve(Photo $photo): Photo
    {
        $photo->update(['status' => 'approved']);

        ProcessApprovedPhoto::dispatch($photo->id);

        if ($photo->is_primary && $photo->user) {
            $photo->user->update(['is_verified' => true]);
        }

        if ($photo->user) {
            $photo->user->notify(new PhotoModerated($photo->id, $photo->user_id, 'approved', 1));
        }

        return $photo;
    }

    /**
     * Отклонить единичное фото (с полным удалением файлов).
     */
    public function reject(Photo $photo): void
    {
        $userId = $photo->user_id;
        $user = $photo->user;

        $photo->deleteFiles();
        $photo->delete();

        if ($user) {
            $user->notify(new PhotoModerated($photo->id, $userId, 'rejected', 1));
        }
    }

    /**
     * Удалить фото из архива (уже одобренное).
     */
    public function destroy(Photo $photo): void
    {
        $userId = $photo->user_id;
        $user = $photo->user;

        $photo->deleteFiles();
        $photo->delete();

        if ($user) {
            $user->notify(new PhotoModerated($photo->id, $userId, 'deleted', 1));
        }
    }

    /**
     * Сделать фото главным (аватаркой).
     */
    public function setPrimary(Photo $photo): void
    {
        DB::transaction(function () use ($photo) {
            Photo::where('user_id', $photo->user_id)->update(['is_primary' => false]);
            $photo->update(['is_primary' => true]);
        });
    }

    /**
     * ОДОБРИТЬ ВСЕ фото конкретного юзера разом.
     */
    public function approveAllForUser(User $user): int
    {
        $photoIds = $user->photos()->where('status', 'pending')->pluck('id');

        if ($photoIds->isEmpty()) return 0;

        DB::transaction(function () use ($photoIds, $user) {
            Photo::whereIn('id', $photoIds)->update(['status' => 'approved']);

            $hasPrimary = Photo::whereIn('id', $photoIds)->where('is_primary', true)->exists();
            if ($hasPrimary) {
                $user->update(['is_verified' => true]);
            }
        });

        foreach ($photoIds as $id) {
            ProcessApprovedPhoto::dispatch($id);
        }

        $user->notify(new PhotoModerated($photoIds->first(), $user->id, 'approved', $photoIds->count()));

        return $photoIds->count();
    }

    /**
     * ОТКЛОНИТЬ ВСЕ фото конкретного юзера разом (с удалением файлов).
     */
    public function rejectAllForUser(User $user): int
    {
        $photos = $user->photos()->where('status', 'pending')->get();

        if ($photos->isEmpty()) return 0;

        $photoIds = $photos->pluck('id');

        DB::transaction(function () use ($photos, $photoIds) {
            foreach ($photos as $photo) {
                $photo->deleteFiles();
            }
            Photo::whereIn('id', $photoIds)->delete();
        });

        $user->notify(new PhotoModerated($photoIds->first(), $user->id, 'rejected', $photos->count()));

        return $photos->count();
    }
}