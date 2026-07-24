<?php

namespace App\Services;

use App\Models\User;
use App\Models\Photo;
use App\Jobs\ProcessApprovedPhoto;
use App\Notifications\PhotoModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PhotoModerationService
{
    /**
     * Одобрить все фото пользователя
     */
    public function approveAll(User $user): array
    {
        $photos = $user->photos()->where('status', 'pending')->get();
        $count = $photos->count();

        if ($count === 0) {
            return ['success' => false, 'message' => 'Нет фото для одобрения.'];
        }

        $firstPhotoId = $photos->first()->id;

        //  Диспатчим джобы до транзакции
        foreach ($photos as $photo) {
            ProcessApprovedPhoto::dispatch($photo->id);
        }

        //  Уведомление после диспатча
        $user->notify(new PhotoModerated($firstPhotoId, $user->id, 'approved', $count));

        return [
            'success' => true,
            'message' => "Все фото пользователя {$user->name} ({$count} шт.) отправлены на обработку.",
            'count' => $count
        ];
    }

    /**
     * Отклонить все фото пользователя
     */
    public function rejectAll(User $user): array
    {
        $photos = $user->photos()->where('status', 'pending')->get();
        $count = $photos->count();

        if ($count === 0) {
            return ['success' => false, 'message' => 'Нет фото для отклонения.'];
        }

        $firstPhotoId = $photos->first()->id;

        //  Транзакция для удаления
        DB::transaction(function () use ($photos) {
            foreach ($photos as $photo) {
                // Удаляем файлы
                if (!filter_var($photo->path, FILTER_VALIDATE_URL)) {
                    Storage::disk('public')->delete($photo->path);
                }
                
                // Мягкое удаление вместо жесткого
                $photo->delete(); // SoftDelete
            }
        });

        //  Уведомление после транзакции
        $user->notify(new PhotoModerated($firstPhotoId, $user->id, 'rejected', $count));

        return [
            'success' => true,
            'message' => "Все фото пользователя {$user->name} ({$count} шт.) отклонены.",
            'count' => $count
        ];
    }

    /**
     * Одобрить одно фото
     */
    public function approveSingle(Photo $photo): array
    {
        if ($photo->status !== 'pending') {
            return ['success' => false, 'message' => 'Фото уже обработано.'];
        }

        ProcessApprovedPhoto::dispatch($photo->id);
        $photo->user->notify(new PhotoModerated($photo->id, $photo->user_id, 'approved', 1));

        return [
            'success' => true,
            'message' => 'Фото одобрено. Запущена обработка...'
        ];
    }

    /**
     * Отклонить одно фото
     */
    public function rejectSingle(Photo $photo): array
    {
        if ($photo->status !== 'pending') {
            return ['success' => false, 'message' => 'Фото уже обработано.'];
        }

        DB::transaction(function () use ($photo) {
            if (!filter_var($photo->path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($photo->path);
            }
            $photo->delete(); // SoftDelete
        });

        $photo->user->notify(new PhotoModerated($photo->id, $photo->user_id, 'rejected', 1));

        return [
            'success' => true,
            'message' => 'Фото отклонено.'
        ];
    }

    /**
     * Удалить фото (для approved/all)
     */
    public function deletePhoto(Photo $photo): array
    {
        DB::transaction(function () use ($photo) {
            // Удаляем все версии файлов
            $paths = [
                $photo->path,
                $photo->path_original,
                $photo->path_large,
                $photo->path_medium,
                $photo->path_thumb
            ];
            
            foreach ($paths as $path) {
                if ($path && !filter_var($path, FILTER_VALIDATE_URL)) {
                    Storage::disk('public')->delete($path);
                }
            }
            
            $photo->delete(); // SoftDelete
        });

        $photo->user->notify(new PhotoModerated($photo->id, $photo->user_id, 'deleted', 1));

        return [
            'success' => true,
            'message' => 'Фото удалено.'
        ];
    }

    /**
     * Установить основное фото
     */
    public function setPrimary(Photo $photo): array
    {
        Photo::where('user_id', $photo->user_id)->update(['is_primary' => false]);
        $photo->update(['is_primary' => true]);
        
        return [
            'success' => true,
            'message' => 'Фото установлено как основное.'
        ];
    }
}