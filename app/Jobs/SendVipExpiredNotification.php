<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendVipExpiredNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        protected User $user,
        protected string $planName = 'VIP'
    ) {}

    public function handle(): void
    {
        // Если юзер успел купить VIP снова, пока джоба лежала в очереди — не шлем письмо
        if ($this->user->is_premium) {
            return;
        }

        $this->user->notify(new SubscriptionExpiredNotification($this->planName));
    }
}