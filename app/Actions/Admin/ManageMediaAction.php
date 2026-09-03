<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Media;
use App\Models\User;

class ManageMediaAction
{
    /**
     * Безопасное удаление файла (с чисткой вариантов).
     */
    public function delete(Media $media, User $admin): void
    {
        $before = [
            'file_name' => $media->file_name, 
            'collection' => $media->collection, 
            'size' => $media->size
        ];

        $after = [
            'status' => 'destroyed', 
            'deleted_by' => $admin->id,
            'context' => [
                'media_id' => $media->id,
                'file_name' => $media->file_name                
            ]
        ];

        // ФИКС: Передаем саму модель $media, чтобы лог привязался к ID
        AdminLog::record('media.delete', $media, $admin, $before, $after);

        // Вызываем метод модели, который чистит файлы с диска
        $media->safeDelete();
    }

    /**
     * Логирование массовой загрузки медиа.
     */
    public function logUpload(array $mediaIds, string $collection, User $admin): void
    {
        if (empty($mediaIds)) return;

        // ФИКС: Берем первую модель из загруженных, чтобы привязать лог к конкретному ID
        $firstMedia = Media::find($mediaIds[0]);

        $after = [
            'status' => 'created', 
            'count' => count($mediaIds),
            'uploaded_by' => $admin->id,
            'context' => [
                'collection' => $collection,
                'media_ids' => $mediaIds                
            ]
        ];

        // Передаем $firstMedia (может быть null, если файлы еще в обработке, но обычно он уже есть в БД)
        AdminLog::record('media.upload', $firstMedia, $admin, null, $after);
    }
}