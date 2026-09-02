<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Gift;
use App\Models\UserGift;
use App\Models\User;

class ManageGiftsAction
{
    /**
     * Создать подарок в каталоге.
     */
    public function createGift(array $data, User $admin): Gift
    {
        $gift = Gift::create($data);
        
        $after = [
            'status' => 'created', 
            'context' => [
                'gift_id' => $gift->id,
                'name' => $gift->name,
                'price' => $gift->price,
                'is_active' => $gift->is_active
            ]
        ];

        AdminLog::record('gift.create', $gift, $admin, null, $after);
        
        return $gift;
    }

    /**
     * Обновить подарок в каталоге.
     */
    public function updateGift(Gift $gift, array $data, User $admin): Gift
    {
        $before = $gift->getOriginal(['name', 'slug', 'image_url', 'price', 'category', 'is_active']);
        $gift->update($data);
        $gift->refresh();

        $after = [
            'name' => $gift->name,
            'slug' => $gift->slug,
            'price' => $gift->price,
            'is_active' => $gift->is_active,
            'context' => [
                'gift_id' => $gift->id,
                'name' => $gift->name
            ]
        ];

        AdminLog::record('gift.update', $gift, $admin, $before, $after);
        
        return $gift;
    }

    /**
     * Удалить подарок (или скрыть, если уже дарили).
     */
    public function deleteGift(Gift $gift, User $admin): bool
    {
        $sentCount = UserGift::where('gift_id', $gift->id)->count();
        
        if ($sentCount > 0) {
            $before = ['is_active' => $gift->getOriginal('is_active')];
            $gift->update(['is_active' => false]);
            $gift->refresh();

            $after = [
                'is_active' => false, 
                'deactivated_by' => $admin->id,
                'context' => [
                    'gift_id' => $gift->id,
                    'name' => $gift->name,
                    'sent_count' => $sentCount
                ]
            ];
            
            AdminLog::record('gift.deactivate', $gift, $admin, $before, $after);
            
            return false; // Возвращаем false, значит не удалено, а скрыто
        }

        $giftId = $gift->id;
        $giftName = $gift->name;
        
        $after = [
            'status' => 'destroyed', 
            'deleted_by' => $admin->id,
            'context' => [
                'gift_id' => $giftId,
                'name' => $giftName
            ]
        ];

        AdminLog::record('gift.delete', $gift, $admin, null, $after);
        $gift->delete();
        
        return true; // Удалено физически
    }

    /**
     * Скрыть/Показать подарок в каталоге.
     */
    public function toggleStatus(Gift $gift, User $admin): void
    {
        $before = ['is_active' => $gift->getOriginal('is_active')];
        $gift->update(['is_active' => !$gift->is_active]);
        $gift->refresh();

        $after = [
            'is_active' => $gift->is_active, 
            'toggled_by' => $admin->id,
            'context' => [
                'gift_id' => $gift->id,
                'name' => $gift->name
            ]
        ];

        AdminLog::record('gift.toggle_status', $gift, $admin, $before, $after);
    }

    /**
     * Скрыть подарок из профиля юзера (Отзыв).
     */
    public function hideUserGift(UserGift $userGift, User $admin): void
    {
        $before = ['deleted_at' => $userGift->getOriginal('deleted_at')];
        $userGift->delete();
        $userGift->refresh();

        $after = [
            'deleted_at' => now()->toDateTimeString(), 
            'hidden_by' => $admin->id,
            'context' => [
                'user_gift_id' => $userGift->id,
                'gift_id' => $userGift->gift_id,
                'sender_id' => $userGift->sender_id,
                'receiver_id' => $userGift->receiver_id,
                'snapshot_name' => $userGift->snapshot_name
            ]
        ];

        // Пишем в логи обоим участникам сделки
        $participants = array_filter([$userGift->sender_id, $userGift->receiver_id]);

        AdminLog::record('user_gift.hide', $userGift, $admin, $before, $after, participants: $participants);
    }

    /**
     * Вернуть скрытый подарок в профиль юзера.
     */
    public function restoreUserGift(UserGift $userGift, User $admin): void
    {
        $before = ['deleted_at' => $userGift->getOriginal('deleted_at')];
        $userGift->restore();
        $userGift->refresh();

        $after = [
            'deleted_at' => null, 
            'restored_by' => $admin->id,
            'context' => [
                'user_gift_id' => $userGift->id,
                'gift_id' => $userGift->gift_id,
                'sender_id' => $userGift->sender_id,
                'receiver_id' => $userGift->receiver_id,
                'snapshot_name' => $userGift->snapshot_name
            ]
        ];

        $participants = array_filter([$userGift->sender_id, $userGift->receiver_id]);

        AdminLog::record('user_gift.restore', $userGift, $admin, $before, $after, participants: $participants);
    }
}