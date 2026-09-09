<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Media;
use App\Models\User;

class ManageMediaAction
{
    /**
     * Одиночное удаление файла (с чисткой вариантов).
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

        AdminLog::record('media.delete', $media, $admin, $before, $after);

        $media->safeDelete();
    }

    /**
     * МАССОВОЕ УДАЛЕНИЕ ФАЙЛОВ (1 запрос в лог, вместо 100)
     */
    public function bulkDelete(array $mediaIds, User $admin): int
    {
        if (empty($mediaIds)) return 0;

        $medias = Media::whereIn('id', $mediaIds)->get();
        $deletedCount = 0;

        foreach ($medias as $media) {
            $media->safeDelete();
            $deletedCount++;
        }

        AdminLog::record('media.delete_bulk', null, $admin, null, [
            'status' => 'destroyed',
            'count' => $deletedCount,
            'deleted_by' => $admin->id,
            'context' => [
                'media_ids' => array_slice($mediaIds, 0, 100) // Пишем только первые 100 ID
            ]
        ]);

        return $deletedCount;
    }

    /**
     * Логирование загрузки медиа (умное разделение одиночной и массовой).
     */
    public function logUpload(array $mediaIds, string $collection, User $admin): void
    {
        if (empty($mediaIds)) return;

        $count = count($mediaIds);
        
        // ФИКС: Различаем одиночную и массовую загрузку!
        $actionName = $count === 1 ? 'media.upload' : 'media.upload_bulk';

        // ФИКС: Берем первую модель из загруженных только если загрузка одиночная, 
        // чтобы привязать лог к конкретному ID. При массовой передаем null.
        $loggableMedia = $count === 1 ? Media::find($mediaIds[0]) : null;

        $after = [
            'status' => 'created', 
            'count' => $count,
            'uploaded_by' => $admin->id,
            'context' => [
                'collection' => $collection,
                // ФИКС: Ограничиваем массив ID до 100, чтобы не положить БД логов при загрузке 10к файлов
                'media_ids' => array_slice($mediaIds, 0, 100)                
            ]
        ];

        AdminLog::record($actionName, $loggableMedia, $admin, null, $after);
    }
}