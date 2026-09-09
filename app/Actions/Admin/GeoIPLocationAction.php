<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\GeoIPLocation;
use App\Models\User;
use App\Services\GeoIPBlockService;
use Illuminate\Support\Facades\Cache;

class GeoIPLocationAction
{
    public function __construct(
        protected GeoIPBlockService $blockService
    ) {}

    public function toggleRegistration(GeoIPLocation $location, User $admin): void
    {
        $before = ['is_registration_blocked' => $location->getOriginal('is_registration_blocked')];
        
        $location->update(['is_registration_blocked' => !$location->is_registration_blocked]);
        
        $this->blockService->clearCache();
        Cache::forget('admin_geo_counts'); // ФИКС: Сброс кэша счетчиков админки

        $after = [
            'is_registration_blocked' => $location->is_registration_blocked, 
            'toggled_by' => $admin->id,
            'context' => $this->prepareLogData($location, $admin)
        ];

        AdminLog::record('geo.toggle_registration', $location, $admin, $before, $after);
    }

    public function toggleFeed(GeoIPLocation $location, User $admin): void
    {
        $before = ['is_feed_blocked' => $location->getOriginal('is_feed_blocked')];
        
        $location->update(['is_feed_blocked' => !$location->is_feed_blocked]);
        
        $this->blockService->clearCache();
        Cache::forget('admin_geo_counts'); // ФИКС: Сброс кэша счетчиков админки

        $after = [
            'is_feed_blocked' => $location->is_feed_blocked, 
            'toggled_by' => $admin->id,
            'context' => $this->prepareLogData($location, $admin)
        ];

        AdminLog::record('geo.toggle_feed', $location, $admin, $before, $after);
    }

    private function prepareLogData(GeoIPLocation $location, User $admin): array
    {
        $data = [
            'location_id' => $location->id,
            'location_name' => $location->name,
            'iso_code' => $location->iso_code ?? '—',
            'type' => $location->type,
            'admin_id' => $admin->id
        ];
        
        if ($location->parent_id) {
            $location->loadMissing('parent');
            if ($location->parent) {
                $parentIso = $location->parent->iso_code ?? '—';
                $data['parent_location'] = "{$location->parent->name} ({$parentIso})";
            }
        }
        
        return $data;
    }
}