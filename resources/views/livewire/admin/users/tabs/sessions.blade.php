<?php

use App\Actions\Admin\ManageUserSessionsAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component 
{
    public int $userId;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    #[Computed]
    public function sessions()
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $this->userId)
            ->orderByDesc('last_activity')
            ->get();

        return $sessions->map(function ($session) {
            $ua = $session->user_agent;
            
            $device = 'Устройство';
            if (preg_match('/(iPad|iPhone|Android|Windows Phone)/i', $ua)) {
                $device = preg_match('/(iPad)/i', $ua) ? 'iPad' : (preg_match('/(iPhone)/i', $ua) ? 'iPhone' : (preg_match('/(Android)/i', $ua) ? 'Android' : 'Mobile'));
            } else {
                $device = 'Компьютер';
            }

            $browser = 'Браузер';
            if (preg_match('/(Chrome|CriOS)/i', $ua) && !preg_match('/(Edg|OPR)/i', $ua)) $browser = 'Chrome';
            elseif (preg_match('/(Safari)/i', $ua) && !preg_match('/(Chrome)/i', $ua)) $browser = 'Safari';
            elseif (preg_match('/(Edg)/i', $ua)) $browser = 'Edge';
            elseif (preg_match('/(Firefox|FxiOS)/i', $ua)) $browser = 'Firefox';
            elseif (preg_match('/(OPR|Opera)/i', $ua)) $browser = 'Opera';

            $isMobile = in_array($device, ['iPad', 'iPhone', 'Android', 'Mobile']);

            return [
                'id' => $session->id,
                'ip' => $session->ip_address,
                'device' => $device,
                'browser' => $browser,
                'is_mobile' => $isMobile,
                'last_activity' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'exact_time' => Carbon::createFromTimestamp($session->last_activity)->format('d.m.Y H:i:s'),
            ];
        });
    }

    #[On('user-action-performed')] 
    public function refreshSessions(): void
    {
        unset($this->sessions);
    }

    /**
     * Убить конкретную сессию.
     */
    public function killSession(string $sessionId, ManageUserSessionsAction $action): void
    {
        $success = $action->killSession($this->user, $sessionId, auth()->user());

        if ($success) {
            $this->dispatch('show-toast', type: 'success', message: 'Сессия завершена. Устройство отключено.');
        } else {
            $this->dispatch('show-toast', type: 'error', message: 'Сессия не найдена или уже завершена.');
        }
        
        unset($this->sessions);
    }

    /**
     * Убить ВСЕ сессии юзера (кнопка паники при взломе).
     */
    public function killAllSessions(ManageUserSessionsAction $action): void
    {
        $count = $action->killAllSessions($this->user, auth()->user());

        if ($count > 0) {
            $this->dispatch('show-toast', type: 'warning', message: "Все устройства ({$count} шт.) отключены от аккаунта.");
        } else {
            $this->dispatch('show-toast', type: 'info', message: 'Активных сессий не найдено.');
        }
        
        unset($this->sessions);
    }
}; 
?>

<div class="space-y-6">

    {{-- Блок последнего входа --}}
    <h3 class="text-sm font-semibold flex items-center gap-2 mb-3">
        <x-lucide-history class="w-4 h-4" /> Последний вход в систему
    </h3>

    <div class="p-4 bg-muted/20 rounded-lg border border-border">        
        <div class="flex items-center gap-6 flex-wrap">
            <div class="flex items-center gap-2 text-sm">
                <x-lucide-clock class="w-4 h-4 text-muted-foreground" />
                <span class="font-medium">{{ $this->user->last_login_at ? $this->user->last_login_at->format('d.m.Y H:i:s') : 'Никогда' }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <x-lucide-globe class="w-4 h-4 text-muted-foreground" />
                <span class="font-mono text-muted-foreground">{{ $this->user->last_login_ip ?? 'Нет данных' }}</span>
            </div>
        </div>
        @if(!$this->user->last_login_at)
            <p class="text-xs text-muted-foreground mt-2 italic">Возможно, аккаунт создан через сидер или админку, без реальной авторизации.</p>
        @endif
    </div>

    {{-- Блок активных сессий --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-sm font-semibold flex items-center gap-2">
                <x-lucide-shield-check class="w-4 h-4" /> Активные сессии
            </h3>
            <p class="text-xs text-muted-foreground mt-1">Устройства, на которых пользователь находится прямо сейчас.</p>
        </div>

        @if($this->sessions->isNotEmpty())
            <x-ui.alert-dialog>
                <x-ui.alert-dialog-trigger>
                    <x-ui.button variant="destructive" size="sm" wire:loading.attr="disabled" wire:target="killAllSessions">
                        <x-lucide-power class="w-4 h-4" wire:loading.remove wire:target="killAllSessions" />
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="killAllSessions" />
                        Завершить все сессии
                    </x-ui.button>
                </x-ui.alert-dialog-trigger>
                <x-ui.alert-dialog-content>
                    <x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-title>Завершить все сессии?</x-ui.alert-dialog-title>
                        <x-ui.alert-dialog-description>
                            Пользователь будет немедленно разлогинен на всех устройствах. Используйте при подозрении на взлом аккаунта.
                        </x-ui.alert-dialog-description>
                    </x-ui.alert-dialog-header>
                    <x-ui.alert-dialog-footer>
                        <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                        <x-ui.alert-dialog-action wire:click="killAllSessions">Завершить все</x-ui.alert-dialog-action>
                    </x-ui.alert-dialog-footer>
                </x-ui.alert-dialog-content>
            </x-ui.alert-dialog>
        @endif
    </div>

    @if($this->sessions->isEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-dashed border-border text-center text-xs text-muted-foreground">
            Нет активных сессий. Пользователь сейчас не в сети.
        </div>
    @else
        <div class="space-y-3">
            @foreach($this->sessions as $session)
                <div class="flex items-center justify-between p-4 bg-card border border-border rounded-lg shadow-xs gap-4" wire:key="session-{{ $session['id'] }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="p-2 rounded-md bg-muted shrink-0">
                            @if($session['is_mobile'])
                                <x-lucide-smartphone class="w-5 h-5 text-blue-500" />
                            @else
                                <x-lucide-monitor class="w-5 h-5 text-muted-foreground" />
                            @endif
                        </div>
                        
                        <div class="flex flex-col min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{ $session['device'] }}</span>
                                <span class="text-xs text-muted-foreground">•</span>
                                <span class="text-xs text-muted-foreground">{{ $session['browser'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-muted-foreground">
                                <span class="font-mono">{{ $session['ip'] }}</span>
                                <span>•</span>
                                <span title="Точное время: {{ $session['exact_time'] }}">{{ $session['last_activity'] }}</span>
                            </div>
                        </div>
                    </div>

                    <x-ui.button variant="ghost" size="sm" wire:click="killSession('{{ $session['id'] }}')" wire:confirm="Завершить эту сессию?" wire:loading.attr="disabled" wire:target="killSession('{{ $session['id'] }}')" class="text-destructive hover:text-destructive shrink-0">
                        <x-lucide-x class="w-4 h-4" wire:loading.remove wire:target="killSession('{{ $session['id'] }}')" />
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="killSession('{{ $session['id'] }}')" />
                        Завершить
                    </x-ui.button>
                </div>
            @endforeach
        </div>
    @endif
</div>