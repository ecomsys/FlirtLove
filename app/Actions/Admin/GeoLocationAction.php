<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\GeoLocation;
use App\Services\GeoBlockService;
use Illuminate\Support\Facades\Auth;

class GeoLocationAction
{
    /**
     * Переключить запрет регистрации для локации.
     */
    public function toggleRegistration(GeoLocation $location): void
    {
        $before = $location->only(['id', 'name', 'type', 'is_registration_blocked']);
        
        $location->update(['is_registration_blocked' => !$location->is_registration_blocked]);
        
        $after = $location->fresh()->only(['id', 'name', 'type', 'is_registration_blocked']);

        app(GeoBlockService::class)->clearCache();

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
    public function toggleFeed(GeoLocation $location): void
    {
        $before = $location->only(['id', 'name', 'type', 'is_feed_blocked']);
        
        $location->update(['is_feed_blocked' => !$location->is_feed_blocked]);
        
        $after = $location->fresh()->only(['id', 'name', 'type', 'is_feed_blocked']);

        app(GeoBlockService::class)->clearCache();

        AdminLog::record(
            'geo.toggle_feed',
            $location,
            Auth::user(),
            $before,
            $after
        );
    }
}