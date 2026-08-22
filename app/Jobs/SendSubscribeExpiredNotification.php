<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSubscribeExpiredNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        protected User $user,
        protected string $planName = 'VIP',
        protected string $tier = 'vip' // Добавили тип подписки
    ) {}

    public function handle(): void
    {
        // Защита от гонки: проверяем, не купил ли юзер ЗА НОВУЮ подписку этого же типа, пока джоба лежала в очереди
        $hasActiveSub = $this->tier === 'premium' 
            ? $this->user->has_active_premium 
            : $this->user->has_active_vip;

        if ($hasActiveSub) {
            return; // Подписка обновлена, не шлем письмо об истечении
        }

        $this->user->notify(new SubscriptionExpiredNotification($this->planName));
    }
}