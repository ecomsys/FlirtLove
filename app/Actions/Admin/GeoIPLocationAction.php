<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\GeoIPLocation;
use App\Models\User;
use App\Services\GeoIPBlockService;

class GeoIPLocationAction
{
    /**
     * Переключить запрет регистрации для локации.
     */
    public function toggleRegistration(GeoIPLocation $location, User $admin): void
    {
        $before = ['is_registration_blocked' => $location->getOriginal('is_registration_blocked')];
        
        $location->update(['is_registration_blocked' => !$location->is_registration_blocked]);
        $location->refresh();
        
        // Сохраняем твою очистку кэша!
        app(GeoIPBlockService::class)->clearCache();

        $after = [
            'is_registration_blocked' => $location->is_registration_blocked, 
            'toggled_by' => $admin->id,
            'context' => $this->prepareLogData($location, $admin)
        ];

        AdminLog::record('geo.toggle_registration', $location, $admin, $before, $after);
    }

    /**
     * Переключить скрытие из ленты для локации.
     */
    public function toggleFeed(GeoIPLocation $location, User $admin): void
    {
        $before = ['is_feed_blocked' => $location->getOriginal('is_feed_blocked')];
        
        $location->update(['is_feed_blocked' => !$location->is_feed_blocked]);
        $location->refresh();
        
        // Сохраняем твою очистку кэша!
        app(GeoIPBlockService::class)->clearCache();

        $after = [
            'is_feed_blocked' => $location->is_feed_blocked, 
            'toggled_by' => $admin->id,
            'context' => $this->prepareLogData($location, $admin)
        ];

        AdminLog::record('geo.toggle_feed', $location, $admin, $before, $after);
    }

    /**
     * Хелпер для формирования красивого контекста лога с привязкой к родителю.
     */
    private function prepareLogData(GeoIPLocation $location, User $admin): array
    {
        $data = [
            'location_id' => $location->id,
            'location_name' => $location->name,
            'iso_code' => $location->iso_code,
            'type' => $location->type,
            'admin_id' => $admin->id
        ];
        
        // Если есть родитель (например, страна у региона), добавляем его в лог для контекста
        if ($location->parent_id) {
            $parent = $location->parent;
            if ($parent) {
                // ФИКС: Вынесли ?? в отдельную переменную, чтобы PHP не ругался на синтаксис
                $parentIso = $parent->iso_code ?? '—';
                $data['parent_location'] = "{$parent->name} ({$parentIso})";
            }
        }
        
        return $data;
    }
}