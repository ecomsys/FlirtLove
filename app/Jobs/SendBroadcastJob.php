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

    public $timeout = 300; 

    public function __construct(
        public int $broadcastId,
        public array $targetAudience
    ) {
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

            $broadcast->markAsSending($totalRecipients);

            // ФИКС: modelKeys() быстрее, чем pluck('id'), так как мы уже выбрали только id
            $query->select('id')->chunkById(100, function ($users) use ($broadcast) {
                SendBroadcastChunkJob::dispatch($broadcast->id, $users->modelKeys())->onQueue('broadcasts');
            });

        } catch (\Exception $e) {
            Log::error('Broadcast dispatcher failed', ['broadcast_id' => $broadcast->id, 'error' => $e->getMessage()]);
            $broadcast->markAsFailed();
        }
    }

    protected function buildTargetQuery(array $targetAudience, string $broadcastType): \Illuminate\Database\Eloquent\Builder
    {
        $query = User::query();
        $query->where('role', User::ROLE_USER);

        if ($broadcastType === 'push') {
            $query->whereNotIn('status', [User::STATUS_BANNED, User::STATUS_SHADOWBANNED]);
        } else {
            $query->whereNotIn('status', [User::STATUS_BANNED]); 
        }

        if (!empty($targetAudience['user_id'])) {
            $query->where('id', $targetAudience['user_id']);
            return $query;
        }

        // ФИКС: Премиум статус
        if (isset($targetAudience['is_premium'])) {
            $isPremium = filter_var($targetAudience['is_premium'], FILTER_VALIDATE_BOOLEAN);
            if ($isPremium) {
                $query->where('premium_expires_at', '>', now());
            } else {
                $query->where(function($q) {
                    $q->whereNull('premium_expires_at')->orWhere('premium_expires_at', '<=', now());
                });
            }
        }
        
        if (!empty($targetAudience['last_seen_days'])) {
            $query->where('last_seen', '<=', now()->subDays((int)$targetAudience['last_seen_days']));
        }
        
        if (isset($targetAudience['has_photo'])) {
            $hasPhoto = filter_var($targetAudience['has_photo'], FILTER_VALIDATE_BOOLEAN);
            if ($hasPhoto) {
                $query->has('photos');
            } else {
                $query->doesntHave('photos');
            }
        }

        // ФИКС: Объединяем все фильтры профиля в один whereHas, чтобы сделать только 1 JOIN
        $profileFilters = [];
        
        if (!empty($targetAudience['gender'])) {
            $profileFilters[] = fn($q) => $q->where('gender', $targetAudience['gender']);
        }
        
        if (!empty($targetAudience['city'])) {
            $profileFilters[] = fn($q) => $q->whereHas('city', fn($cq) => $cq->where('name', 'ilike', "%{$targetAudience['city']}%"));
        }
        
        if (!empty($targetAudience['age_from']) || !empty($targetAudience['age_to'])) {
            $profileFilters[] = function ($q) use ($targetAudience) {
                if (!empty($targetAudience['age_from'])) {
                    $q->where('birth_date', '<=', now()->subYears($targetAudience['age_from']));
                }
                if (!empty($targetAudience['age_to'])) {
                    $q->where('birth_date', '>=', now()->subYears($targetAudience['age_to']));
                }
            };
        }

        if (!empty($profileFilters)) {
            $query->whereHas('profile', function ($q) use ($profileFilters) {
                foreach ($profileFilters as $filter) {
                    $filter($q);
                }
            });
        }

        return $query;
    }
}