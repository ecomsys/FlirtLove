<?php

namespace App\Actions\Admin\Photos;

use App\Jobs\ProcessApprovedPhoto;
use App\Models\Photo;
use App\Models\User;
use App\Notifications\PhotoModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ModeratePhotoAction
{
    /**
     * Одобрить единичное фото.
     */
    public function approve(Photo $photo): Photo
    {
        $photo->update(['status' => 'approved']);

        // Твой Job для создания WebP версий
        ProcessApprovedPhoto::dispatch($photo->id);

        // Верификация, если это аватарка
        if ($photo->is_primary && $photo->user) {
            $photo->user->update(['is_verified' => true]);
        }

        // Уведомление юзера (используем твою структуру)
        if ($photo->user) {
            $photo->user->notify(new PhotoModerated($photo->id, $photo->user_id, 'approved', 1));
        }

        return $photo;
    }

    /**
     * Отклонить единичное фото (с полным удалением файлов).
     */
    public function reject(Photo $photo): Photo
    {
        $userId = $photo->user_id;
        $user = $photo->user;

        // 1. Удаляем файлы с диска (используем наш метод из модели Photo)
        $photo->deleteFiles();

        // 2. Удаляем запись из БД
        $photo->delete();

        // Уведомление
        if ($user) {
            $user->notify(new PhotoModerated($photo->id, $userId, 'rejected', 1));
        }

        return $photo;
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
            // Снимаем флаг у старой аватарки
            Photo::where('user_id', $photo->user_id)->update(['is_primary' => false]);
            // Ставим новой
            $photo->update(['is_primary' => true]);
        });
    }

    /**
     * ОДОБРИТЬ ВСЕ фото конкретного юзера разом.
     */
    public function approveAllForUser(User $user): int
    {
        $photos = $user->photos()->where('status', 'pending')->get();
        $count = $photos->count();

        if ($count === 0) return 0;

        // Обновляем статус в БД разом
        $user->photos()->where('status', 'pending')->update(['status' => 'approved']);

        // Отправляем Job на обработку каждого фото
        foreach ($photos as $photo) {
            ProcessApprovedPhoto::dispatch($photo->id);
        }

        // Уведомление (отправляем ID первого фото и количество)
        $user->notify(new PhotoModerated($photos->first()->id, $user->id, 'approved', $count));

        return $count;
    }

    /**
     * ОТКЛОНИТЬ ВСЕ фото конкретного юзера разом (с удалением файлов).
     */
    public function rejectAllForUser(User $user): int
    {
        $photos = $user->photos()->where('status', 'pending')->get();
        $count = $photos->count();

        if ($count === 0) return 0;

        // Удаляем физические файлы
        foreach ($photos as $photo) {
            $photo->deleteFiles();
        }

        // Удаляем записи из БД
        $user->photos()->where('status', 'pending')->delete();

        // Уведомление
        $user->notify(new PhotoModerated($photos->first()->id, $user->id, 'rejected', $count));

        return $count;
    }
}