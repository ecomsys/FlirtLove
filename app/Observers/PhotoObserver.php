<?php

namespace App\Observers;

use App\Models\Photo;
use App\Models\User;

class PhotoObserver
{
    public function updated(Photo $photo): void
    {
        // Если фото отклонили, а оно было аватаркой — снимаем is_primary!
        if ($photo->wasChanged('status') && $photo->status === 'rejected' && $photo->is_primary) {
            $photo->update(['is_primary' => false]);
            
            // Можно добавить логику: отправить пуш юзеру "Ваша аватарка отклонена"
        }

        // Если фото удалили (soft delete), а оно было аватаркой — тоже снимаем
        // (хотя в Livewire/контроллерах это можно делать явно, но Observer надежнее)
    }

    public function created(Photo $photo): void
    {
        // Обновляем счетчик фото в альбоме
        if ($photo->album_id) {
            $photo->album?->refreshPhotosCount();
        }
    }

    public function deleted(Photo $photo): void
    {
        // Если удалили (soft delete), обновляем счетчик
        if ($photo->album_id) {
            $photo->album?->refreshPhotosCount();
        }
    }

    public function forceDeleted(Photo $photo): void
    {
        // Физически удаляем файлы с диска (вызов из booted модели тоже это делает, 
        // но лучше дублировать в Observer, если booted когда-нибудь удалят)
        $photo->deleteFiles();
    }
}