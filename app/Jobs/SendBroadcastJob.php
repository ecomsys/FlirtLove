<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Ставим большой таймаут для диспетчера, если юзеров очень много
    public $timeout = 300; 

    public function __construct(
        public int $broadcastId,
        public array $targetAudience
    ) {
        // Отправляем диспетчер в ту же очередь
        $this->onQueue('broadcasts');
    }

    public function handle(): void
    {
        $broadcast = Broadcast::find($this->broadcastId);
        if (!$broadcast) return;

        try {
            $query = $this->buildTargetQuery($this->targetAudience, $broadcast->type);
            
            $totalRecipients = $query->count();

            if ($totalRecipients === 0) {
                Log::warning("Broadcast ID {$this->broadcastId} finished with 0 recipients.", ['filters' => $this->targetAudience]);
                $broadcast->markAsSent();
                return;
            }

            // Обновляем счетчики
            $broadcast->markAsSending($totalRecipients);

            // ДРОБИМ НА МЕЛКИЕ ДЖОБЫ (по 100 юзеров)
            // select('id') экономит память, так как нам нужны только ID для уведомления
            $query->select('id')->chunkById(100, function ($userIds) use ($broadcast) {
                // Диспатчим новую задачу для каждых 100 юзеров
                SendBroadcastChunkJob::dispatch($broadcast->id, $userIds->pluck('id')->toArray())->onQueue('broadcasts');
            });

        } catch (\Exception $e) {
            Log::error('Broadcast dispatcher failed', ['broadcast_id' => $broadcast->id, 'error' => $e->getMessage()]);
            $broadcast->markAsFailed();
        }
    }

    protected function buildTargetQuery(array $targetAudience, string $broadcastType): \Illuminate\Database\Eloquent\Builder
    {
        $query = User::query();
        
        // Базовое условие: только обычные юзеры. Soft Deletes отсекут удаленных.
        $query->where('role', 'user');

        // ИСПРАВЛЕННАЯ ЛОГИКА СТАТУСОВ (взял из твоей миграции)
        if ($broadcastType === 'push') {
            // Для пушей исключаем забаненных и теневых, чтобы не спалить бан
            $query->whereNotIn('status', ['banned', 'shadowbanned']);
        } else {
            // Для In-App и Email шлем всем, кроме забаненных (деактивированные получают системные письма)
            $query->whereNotIn('status', ['banned']); 
        }

        // Если шлем конкретному юзеру по ID
        if (!empty($targetAudience['user_id'])) {
            $query->where('id', $targetAudience['user_id']);
            return $query;
        }

        if (!empty($targetAudience['gender'])) {
            $query->whereHas('profile', fn($q) => $q->where('gender', $targetAudience['gender']));
        }
        
       // VIP статус
        if (isset($targetAudience['is_premium'])) {
            if ($targetAudience['is_premium'] === true || $targetAudience['is_premium'] === 'true') {
                $query->where('is_premium', true)->where('premium_expires_at', '>', now());
            } else {
                // Без VIP (false)
                $query->where(function($q) {
                    $q->where('is_premium', false)->orWhereNull('is_premium');
                });
            }
        }
        
        if (!empty($targetAudience['city'])) {
            $query->whereHas('profile', fn($q) => $q->where('city', 'ilike', "%{$targetAudience['city']}%"));
        }
        
        if (!empty($targetAudience['age_from']) || !empty($targetAudience['age_to'])) {
            $query->whereHas('profile', function ($q) use ($targetAudience) {
                if (!empty($targetAudience['age_from'])) {
                    $q->where('birth_date', '<=', now()->subYears($targetAudience['age_from'])->format('Y-m-d'));
                }
                if (!empty($targetAudience['age_to'])) {
                    $q->where('birth_date', '>=', now()->subYears($targetAudience['age_to'])->format('Y-m-d'));
                }
            });
        }
        
        if (!empty($targetAudience['last_seen_days'])) {
            $query->where('last_seen', '<=', now()->subDays((int)$targetAudience['last_seen_days']));
        }
        
        if (!empty($targetAudience['device_os'])) {
            $query->where('device_os', $targetAudience['device_os']);
        }
        
        // Наличие фото
        if (isset($targetAudience['has_photo'])) {
            if ($targetAudience['has_photo'] === true || $targetAudience['has_photo'] === 'true') {
                $query->has('photos');
            } else {
                // Без фото (false)
                $query->doesntHave('photos');
            }
        }

        return $query;
    }
}