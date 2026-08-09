<?php

use App\Actions\Admin\ToggleUserBanAction;
use App\Enums\BanReason;
use App\Models\AdminLog;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component 
{
    public int $userId;
    public string $ban_reason = 'other';
    public string $ban_type = 'permanent';

    private ToggleUserBanAction $toggleUserBanAction;

    public function boot(ToggleUserBanAction $toggleUserBanAction): void
    {
        $this->toggleUserBanAction = $toggleUserBanAction;
    }

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    // Достаем юзера (хватит и обычного find, так как удаленные сюда не попадут)
    #[Computed]
    public function user(): User
    {
        return User::findOrFail($this->userId);
    }

    // Динамическая история банов (обновляется при действиях)
    #[Computed]
    public function banHistory()
    {
        return AdminLog::where('loggable_type', User::class)
            ->where('loggable_id', $this->userId)
            ->whereIn('action', ['user.ban', 'user.unban', 'user.shadowban', 'user.delete', 'user.restore', 'user.mass_ban', 'user.mass_delete'])
            ->with('admin:id,name,email')
            ->latest()
            ->limit(20)
            ->get();
    }

    // Слушаем обновления из родительского компонента
    #[On('user-action-performed')] 
    public function refreshUser(): void
    {
        unset($this->user);
        unset($this->banHistory);
    }

    /**
     * Применить блокировку.
     */
    public function applyBan(): void
    {
        $reasonEnum = BanReason::tryFrom($this->ban_reason) ?? BanReason::Other;
        $reasonText = $reasonEnum->label();

        $result = $this->toggleUserBanAction->execute($this->user, $reasonText, $this->ban_type);

        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);

        if ($result['success']) {
            $this->ban_type = 'permanent';
            $this->ban_reason = 'other';
            $this->dispatch('user-action-performed');
        }
    }

    /**
     * Снять блокировку.
     */
    public function unbanUser(): void
    {
        $result = $this->toggleUserBanAction->execute($this->user, 'Снят модератором', 'permanent');

        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);

        if ($result['success']) {
            $this->dispatch('user-action-performed');
        }
    }

    /**
     * Хелпер для формирования данных таймлайна из лога.
     */
    public function getLogMeta(AdminLog $log): array
    {
        $title = 'Действие';
        $badge = ['variant' => 'secondary', 'label' => 'Действие'];
        $period = '';
        $icon = 'ban';
        $iconColor = 'text-destructive bg-destructive/10';

        if ($log->action === 'user.unban') {
            $title = 'Разблокирован';
            $badge = ['variant' => 'success', 'label' => 'Разбан'];
            $icon = 'unlock';
            $iconColor = 'text-green-500 bg-green-500/10';
        } elseif ($log->action === 'user.restore') {
            $title = 'Аккаунт восстановлен';
            $badge = ['variant' => 'success', 'label' => 'Восстановление'];
            $icon = 'rotate-ccw';
            $iconColor = 'text-green-500 bg-green-500/10';
        } elseif (in_array($log->action, ['user.delete', 'user.mass_delete'])) {
            $title = 'Деактивирован';
            $badge = ['variant' => 'secondary', 'label' => 'Удален'];
            $icon = 'trash-2';
            $iconColor = 'text-muted-foreground bg-muted';
        } else {
            $afterStatus = $log->after['status'] ?? null;
            $hasUntil = !empty($log->after['banned_until']);

            if ($afterStatus === 'shadowbanned') {
                $title = 'Теневой бан';
                $badge = ['variant' => 'warning', 'label' => 'Теневой'];
                $icon = 'eye-off';
                $iconColor = 'text-purple-500 bg-purple-500/10';
            } elseif ($hasUntil) {
                $title = 'Временный бан';
                $startDate = \Carbon\Carbon::parse($log->created_at);
                $endDate = \Carbon\Carbon::parse($log->after['banned_until']);
                $days = $startDate->diffInDays($endDate);
                $badge = ['variant' => 'destructive', 'label' => "На {$days} дн."];
                $period = "С: <span class='text-foreground'>{$startDate->format('d.m.y H:i')}</span> | По: <span class='text-foreground'>{$endDate->format('d.m.y H:i')}</span>";
            } else {
                $title = 'Заблокирован';
                $badge = ['variant' => 'destructive', 'label' => 'Вечный'];
            }
        }

        return compact('title', 'badge', 'period', 'icon', 'iconColor');
    }
}; 
?>

<div class="space-y-6">
    
    {{-- 1 и 2. СТАТУС И ДЕЙСТВИЯ В ДВЕ КОЛОНКИ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
        
        {{-- 1. ТЕКУЩИЙ СТАТУС --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border h-full">
            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                <x-lucide-info class="w-4 h-4" /> Текущий статус аккаунта
            </h3>
            
            <div class="flex items-center gap-4 flex-wrap">
                @php 
                    $statusConfig = match($this->user->status) {
                        'active' => ['variant' => 'success', 'label' => 'Активен', 'icon' => 'check-circle'],
                        'banned' => ['variant' => 'destructive', 'label' => 'Забанен', 'icon' => 'ban'],
                        'shadowbanned' => ['variant' => 'warning', 'label' => 'Теневой бан', 'icon' => 'eye-off'],
                        default => ['variant' => 'secondary', 'label' => $this->user->status, 'icon' => 'help-circle']
                    };
                @endphp

                <x-ui.badge variant="{{ $statusConfig['variant'] }}" size="lg">
                    <x-dynamic-component component="lucide-{{ $statusConfig['icon'] }}" class="w-4 h-4 inline mr-1" />
                    {{ $statusConfig['label'] }}
                </x-ui.badge>

                @if($this->user->status === 'banned' || $this->user->status === 'shadowbanned')
                    <div class="text-sm text-muted-foreground">
                        @if($this->user->ban_reason)
                            <span class="block">Причина: <span class="text-foreground font-medium">{{ $this->user->ban_reason }}</span></span>
                        @endif
                        
                        @if($this->user->banned_until)
                            <span class="block">Истекает: <span class="text-foreground font-medium">{{ $this->user->banned_until->format('d.m.Y H:i') }}</span> ({{ $this->user->banned_until->diffForHumans() }})</span>
                        @else
                            <span class="block text-destructive font-medium">Бессрочно</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- 2. ДЕЙСТВИЯ (БАН / РАЗБАН) --}}
        <div class="h-full">
            @if($this->user->status === 'banned' || $this->user->status === 'shadowbanned')
                {{-- КНОПКА РАЗБАНА --}}
                <div class="p-4 border border-green-500/30 rounded-lg bg-green-500/5 h-full flex flex-col">
                    <h3 class="text-sm font-semibold mb-3 text-green-600 flex items-center gap-2">
                        <x-lucide-shield-check class="w-4 h-4" /> Снять ограничения
                    </h3>
                    <p class="text-xs text-muted-foreground mb-3 flex-grow">
                        Полностью снять бан/теневой бан и восстановить аккаунт. Пользователь снова сможет заходить в приложение.
                    </p>
                    <x-ui.button wire:click="unbanUser" wire:confirm="Разбанить пользователя?" variant="success" class="w-full">
                        <x-lucide-unlock class="w-4 h-4" /> Разбанить
                    </x-ui.button>
                </div>
            @else
            {{-- ФОРМА БАНА --}}
                <div class="p-4 border border-destructive/30 rounded-lg bg-destructive/5 h-full flex flex-col">
                    <h3 class="text-sm font-semibold mb-1 text-destructive flex items-center gap-2">
                        <x-lucide-shield-x class="w-4 h-4" /> Заблокировать пользователя
                    </h3>
                    
                    <div class="space-y-4 flex-grow">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            
                            {{-- Причина бана --}}
                            <div class="space-y-1.5">
                                <label for="ban_reason" class="text-xs font-medium text-muted-foreground tracking-wider">Причина</label>
                                <x-ui.select wire:model.live="ban_reason">
                                    <x-ui.select-trigger id="ban_reason" class="w-full"><x-ui.select-value placeholder="Выберите причину" /></x-ui.select-trigger>
                                    <x-ui.select-content>
                                        @foreach(BanReason::options() as $value => $label)
                                            <x-ui.select-item value="{{ $value }}" wire:key="reason-{{ $value }}">{{ $label }}</x-ui.select-item>
                                        @endforeach
                                    </x-ui.select-content>
                                </x-ui.select>
                            </div>

                            {{-- Тип бана --}}
                            <div class="space-y-1.5">
                                <label for="ban_type" class="text-xs font-medium text-muted-foreground tracking-wider">Тип блокировки</label>
                                <x-ui.select wire:model.live="ban_type">
                                    <x-ui.select-trigger id="ban_type" class="w-full"><x-ui.select-value placeholder="Выберите тип" /></x-ui.select-trigger>
                                    <x-ui.select-content>                                        
                                        <x-ui.select-item value="shadow">Теневой бан</x-ui.select-item>
                                        <x-ui.select-item value="temp">Бан на 3 дня</x-ui.select-item>
                                        <x-ui.select-item value="permanent">Вечный бан</x-ui.select-item>
                                    </x-ui.select-content>
                                </x-ui.select>
                            </div>
                            
                        </div>
                    </div>

                    <x-ui.button wire:click="applyBan" wire:confirm="Вы уверены, что хотите заблокировать этого пользователя?" variant="destructive" class="w-full mt-4">
                        <x-lucide-ban class="w-4 h-4" /> Применить блокировку
                    </x-ui.button>
                </div>
            @endif
        </div>
    </div>

    {{-- 3. ИСТОРИЯ ДЕЙСТВИЙ (ЖУРНАЛ) --}}
    @if($this->banHistory->isNotEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <h3 class="text-sm font-semibold mb-4 flex items-center gap-2">
                <x-lucide-history class="w-4 h-4" /> История модерации
            </h3>
            
            <div class="space-y-4 relative before:absolute before:left-[15px] before:top-2 before:bottom-2 before:w-px before:bg-border">
                @foreach($this->banHistory as $log)
                    @php $meta = $this->getLogMeta($log); @endphp
                    <div class="flex gap-4 items-start relative">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 shrink-0 {{ $meta['iconColor'] }}">
                            <x-dynamic-component component="lucide-{{ $meta['icon'] }}" class="w-4 h-4" />
                        </div>
                        
                        <div class="flex-1 pt-1">
                            <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">{{ $meta['title'] }}</span>
                                    <x-ui.badge variant="{{ $meta['badge']['variant'] }}" size="xs">{{ $meta['badge']['label'] }}</x-ui.badge>
                                </div>
                                <span class="text-[10px] text-muted-foreground">{{ $log->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                            
                            <div class="text-xs text-muted-foreground">
                                @if(!empty($log->after['ban_reason']))
                                    Причина: <span class="text-foreground">{{ $log->after['ban_reason'] }}</span><br>
                                @endif
                                
                                @if(!empty($meta['period']))
                                    {!! $meta['period'] !!}<br>
                                @endif
                                
                                Админ: 
                                @if($log->admin)
                                    <a href="{{ route('admin.users.show', $log->admin->id) }}" wire:navigate class="text-primary hover:underline">
                                        {{ $log->admin->name }}
                                    </a>
                                @else 
                                    Система
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="p-4 bg-muted/20 rounded-lg border border-dashed border-border text-center text-sm text-muted-foreground">
            История пуста. Пользователь никогда не получал санкций.
        </div>
    @endif
</div>