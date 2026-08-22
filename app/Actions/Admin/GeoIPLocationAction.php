<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\GeoIPLocation;
use App\Services\GeoIPBlockService;
use Illuminate\Support\Facades\Auth;

class GeoIPLocationAction
{
    /**
     * Переключить запрет регистрации для локации.
     */
    public function toggleRegistration(GeoIPLocation $location): void
    {
        $before = $this->prepareLogData($location);
        
        $location->update(['is_registration_blocked' => !$location->is_registration_blocked]);
        
        $after = $this->prepareLogData($location->fresh());

        app(GeoIPBlockService::class)->clearCache();

        AdminLog::record(
            'geo.toggle_registration',
            $location,
            Auth::user(),
            $before,
            $after
        );
    }

    /**
     * Переключить скрытие из ленты для локации.
     */
    public function toggleFeed(GeoIPLocation $location): void
    {
        $before = $this->prepareLogData($location);
        
        $location->update(['is_feed_blocked' => !$location->is_feed_blocked]);
        
        $after = $this->prepareLogData($location->fresh());

        app(GeoIPBlockService::class)->clearCache();

        AdminLog::record(
            'geo.toggle_feed',
            $location,
            Auth::user(),
            $before,
            $after
        );
    }

    /**
     * Хелпер для формирования красивого лога с привязкой к родителю.
     */
    private function prepareLogData(GeoIPLocation $location): array
    {
        $data = $location->only(['id', 'name', 'type', 'is_registration_blocked', 'is_feed_blocked']);
        
        // Если есть родитель (например, страна у региона), добавляем его в лог для контекста
        if ($location->parent_id) {
            $parent = $location->parent;
            if ($parent) {
                $data['parent_location'] = "{$parent->name} ({$parent->iso_code ?? '—'})";
            }
        }
        
        return $data;
    }
}