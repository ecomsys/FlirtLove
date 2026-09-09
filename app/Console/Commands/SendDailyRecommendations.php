<?php

namespace App\Console\Commands;

use App\Models\UserPreference;
use App\Notifications\DailyRecommendationsNotification;
use App\Services\Search\UserSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendDailyRecommendations extends Command
{
    protected $signature = 'users:send-recommendations';
    protected $description = 'Отправляет автоматические рекомендации (пуши и email) неактивным юзерам';

    public function handle(UserSearchService $searchService): void
    {
        $this->info('🚀 Начинаем рассылку рекомендаций...');

        // ФИКС: Ищем тех, кто разрешил ИЛИ пуши ИЛИ email за 1 запрос к БД (вместо двух)
        UserPreference::where(function ($q) {
            $q->where(fn($q2) => $q2->wantsPushRecommendations())
              ->orWhere(fn($q2) => $q2->wantsEmailRecommendations());
        })
        ->whereHas('user', function ($q) {
            $q->where('last_seen', '<', now()->subDay())
              ->where('status', 'active');
        })
        ->with('user')
        ->chunkById(200, function ($preferences) use ($searchService) {
            
            foreach ($preferences as $pref) {
                $user = $pref->user;
                if (!$user) continue;

                $filters = [
                    'gender' => $pref->preferred_gender,
                    'age_from' => $pref->preferred_age_min,
                    'age_to' => $pref->preferred_age_max,
                ];
                
                // Получаем 3 анкеты
                $matches = $searchService->search($user, $filters);
                $matches = collect($matches->items() ?? [])->take(3);

                if ($matches->isNotEmpty()) {
                    // ФИКС: Жадно грузим связи для формирования снапшота (защита от N+1)
                    $matches->loadMissing(['profile.city', 'photos']);

                    // ФИКС: Формируем массив скаляров! Никаких моделей в Redis.
                    $payload = $matches->map(function ($matchUser) {
                        return [
                            'id' => $matchUser->id,
                            'name' => $matchUser->name,
                            'avatar' => $matchUser->avatar_url,
                            'age' => $matchUser->profile?->age ?? '—',
                            'city' => $matchUser->profile?->city?->name ?? 'Город не указан',
                        ];
                    })->values()->toArray();

                    $user->notify(new DailyRecommendationsNotification($payload));
                }
            }
        });

        $this->info('✅ Рассылка рекомендаций завершена!');
    }
}