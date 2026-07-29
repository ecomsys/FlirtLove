<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Models\Photo;
use App\Notifications\PhotoModerated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileShowAction
{
    /**
     * Внедряем наш единый экшен для бана через конструктор
     */
    public function __construct(
        private ToggleUserBanAction $toggleUserBanAction
    ) {}

    /**
     * Получить полный профиль пользователя со всеми связями
     */
    public function getProfile(int $userId): User
    {
        return User::with([
            'profile',
            'preferences',
            'albums' => function ($query) {
                $query->with(['photos' => function ($q) {
                    $q->orderBy('is_primary', 'desc')
                      ->orderBy('position', 'asc')
                      ->orderBy('created_at', 'desc');
                }])->orderBy('is_default', 'desc')->orderBy('name');
            },
            'photos' => function ($q) {
                $q->where('status', 'pending');
            },
            'receivedReports' => fn($q) => $q->where('status', 'pending')->with('user'),
            'sentReports' => fn($q) => $q->where('status', 'pending')->with('reportedUser'),
            'photoComments' => fn($q) => $q->where('status', 'pending')->with('photo'),
        ])->findOrFail($userId);
    }

    /**
     * Получить статистику пользователя
     */
    public function getStats(User $user): array
    {
        return [
            'photos_count' => $user->photos()->count(),
            'pending_photos' => $user->photos()->where('status', 'pending')->count(),
            'comments_count' => $user->photoComments()->count(),
            'pending_comments' => $user->photoComments()->where('status', 'pending')->count(),
            'received_reports' => $user->receivedReports()->count(),
            'pending_received_reports' => $user->receivedReports()->where('status', 'pending')->count(),
            'sent_reports' => $user->sentReports()->count(),
            'matches_count' => $user->matches()->count(),
            'swipes_given' => $user->swipesGiven()->count(),
            'swipes_received' => $user->swipesReceived()->count(),
        ];
    }

    /**
     * Получить адрес по координатам (с кэшированием)
     */
    public function getAddressFromCoords(float $lat, float $lng): ?string
    {
        $cacheKey = "address_{$lat}_{$lng}";
        
        return Cache::remember($cacheKey, 86400, function () use ($lat, $lng) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'LoveClone/1.0',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'zoom' => 18,
                ]);

                if ($response->successful()) {
                    return $response->json()['display_name'] ?? null;
                }
                return null;
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    /**
     * Обновить локацию пользователя
     */
    public function updateLocation(User $user, float $lat, float $lng): array
    {
        $address = $this->getAddressFromCoords($lat, $lng);
        $city = $this->extractCity($address);
        $country = $this->extractCountry($address);

        DB::transaction(function () use ($user, $lat, $lng, $address, $city, $country) {
            if ($user->profile) {
                $user->profile->update([
                    'address' => $address,
                    'city' => $city,
                    'country' => $country,
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"),
                ]);
            } else {
                $user->profile()->create([
                    'address' => $address,
                    'city' => $city,
                    'country' => $country,
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"),
                ]);
            }

            $user->update([
                'latitude' => $lat,
                'longitude' => $lng,
                'address' => $address,
            ]);

            Log::info('Локация пользователя обновлена', [
                'user_id' => $user->id,
                'lat' => $lat,
                'lng' => $lng,
                'address' => $address,
                'admin_id' => auth()->id(),
            ]);
        });

        return [
            'success' => true,
            'lat' => $lat,
            'lng' => $lng,
            'address' => $address,
            'message' => 'Координаты и адрес обновлены',
        ];
    }

    /**
     * Забанить или разбанить пользователя
     * Делегируем работу специализированному экшену ToggleUserBanAction
     *
     * @param User $user
     * @param string $reason Причина бана
     * @return array ['success' => bool, 'is_banned' => bool, 'message' => string]
     */
    public function toggleBan(User $user, string $reason = 'Нарушение правил сервиса'): array
    {
        return $this->toggleUserBanAction->execute($user, $reason);
    }

    /**
     * Получить фото на модерации
     */
    public function getPendingPhotos(User $user)
    {
        return $user->photos()->where('status', 'pending')->get();
    }

    /**
     * Удалить фото пользователя
     */
    public function deletePhoto(User $user, int $photoId): array
    {
        $photo = $user->photos()->find($photoId);
        
        if (!$photo) {
            return ['success' => false, 'message' => 'Фото не найдено'];
        }

        DB::transaction(function () use ($photo, $user) {
            $this->deletePhotoFiles($photo);
            $photo->delete();
            $user->notify(new PhotoModerated($photo->id, $user->id, 'deleted', 1));
            
            Log::info('Фото пользователя удалено администратором', [
                'photo_id' => $photo->id,
                'user_id' => $user->id,
                'admin_id' => auth()->id(),
            ]);
        });

        return ['success' => true, 'message' => 'Фото удалено'];
    }

    /**
     * Установить фото как основное
     */
    public function setPrimaryPhoto(User $user, int $photoId): array
    {
        $photo = $user->photos()->find($photoId);
        
        if (!$photo) {
            return ['success' => false, 'message' => 'Фото не найдено'];
        }

        if ($photo->status !== 'approved') {
            return ['success' => false, 'message' => 'Нельзя сделать неодобренное фото основным'];
        }

        DB::transaction(function () use ($user, $photoId) {
            $user->photos()->update(['is_primary' => false]);
            $user->photos()->where('id', $photoId)->update(['is_primary' => true]);
            
            Log::info('Основное фото пользователя изменено', [
                'photo_id' => $photoId,
                'user_id' => $user->id,
                'admin_id' => auth()->id(),
            ]);
        });

        return ['success' => true, 'message' => 'Фото установлено как основное'];
    }

    /**
     * Извлечь город из адреса
     */
    private function extractCity(?string $address): ?string
    {
        if (empty($address)) return null;
        $parts = explode(',', $address);
        return trim($parts[1] ?? $parts[0] ?? '');
    }

    /**
     * Извлечь страну из адреса
     */
    private function extractCountry(?string $address): ?string
    {
        if (empty($address)) return null;
        $parts = explode(',', $address);
        return trim(end($parts));
    }

    /**
     * Удалить файлы фото с диска
     */
    private function deletePhotoFiles(Photo $photo): void
    {
        $paths = [
            $photo->path_original,
            $photo->path_large,
            $photo->path_medium,
            $photo->path_thumb,
        ];

        foreach (array_filter($paths) as $path) {
            if (!filter_var($path, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}