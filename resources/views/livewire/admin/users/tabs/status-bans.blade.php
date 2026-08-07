<?php

use App\Actions\Admin\ToggleUserBanAction;
use App\Enums\BanReason;
use App\Models\AdminLog;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component 
{
    public User $user;

    /** @var string Выбранная причина бана (значение из Enum) */
    public string $ban_reason = 'other';
    
    /** @var string Тип бана: permanent, temp, shadow */
    public string $ban_type = 'permanent';

    private ToggleUserBanAction $toggleUserBanAction;

    public function boot(ToggleUserBanAction $toggleUserBanAction): void
    {
        $this->toggleUserBanAction = $toggleUserBanAction;
    }

    public function applyBan(): void
    {
        // Достаем читабельный лейбл из Enum для записи в лог и БД
        $reasonEnum = BanReason::tryFrom($this->ban_reason) ?? BanReason::Other;
        $reasonText = $reasonEnum->label();

        $result = $this->toggleUserBanAction->execute($this->user, $reasonText, $this->ban_type);

        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);

        if ($result['success']) {
            $this->reset('ban_reason', 'ban_type');
            $this->ban_type = 'permanent';
            $this->ban_reason = 'other';
            $this->dispatch('user-updated')->to('admin.users.show');
        }
    }

    public function unbanUser(): void
    {
        $result = $this->toggleUserBanAction->execute($this->user, 'Снят модератором', 'permanent');

        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);

        if ($result['success']) {
            $this->dispatch('user-updated')->to('admin.users.show');
        }
    }

    public function with(): array
    {
        $banHistory = AdminLog::where('loggable_type', User::class)
            ->where('loggable_id', $this->user->id)
            ->whereIn('action', ['user.ban', 'user.unban', 'user.shadowban', 'user.delete', 'user.mass_ban', 'user.mass_delete'])
            ->with('admin:id,name,email')
            ->latest()
            ->limit(15)
            ->get();

        return [
            'banHistory' => $banHistory,
            'banReasons' => BanReason::options()
        ];
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
                    $statusConfig = match($user->status) {
                        'active' => ['variant' => 'success', 'label' => 'Активен', 'icon' => 'check-circle'],
                        'banned' => ['variant' => 'destructive', 'label' => 'Забанен', 'icon' => 'ban'],
                        'shadowbanned' => ['variant' => 'warning', 'label' => 'Теневой бан', 'icon' => 'eye-off'],
                        'deactivated' => ['variant' => 'secondary', 'label' => 'Деактивирован', 'icon' => 'trash-2'],
                        default => ['variant' => 'secondary', 'label' => $user->status, 'icon' => 'help-circle']
                    };
                @endphp

                <x-ui.badge variant="{{ $statusConfig['variant'] }}" size="lg">
                    <x-dynamic-component component="lucide-{{ $statusConfig['icon'] }}" class="w-4 h-4 inline mr-1" />
                    {{ $statusConfig['label'] }}
                </x-ui.badge>

                @if($user->status === 'banned' || $user->status === 'shadowbanned')
                    <div class="text-sm text-muted-foreground">
                        @if($user->ban_reason)
                            <span class="block">Причина: <span class="text-foreground font-medium">{{ $user->ban_reason }}</span></span>
                        @endif
                        
                        @if($user->banned_until)
                            <span class="block">Истекает: <span class="text-foreground font-medium">{{ $user->banned_until->format('d.m.Y H:i') }}</span> ({{ $user->banned_until->diffForHumans() }})</span>
                        @else
                            <span class="block text-destructive font-medium">Бессрочно</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- 2. ДЕЙСТВИЯ (БАН / РАЗБАН) --}}
        @if($user->status === 'active')
            {{-- ФОРМА БАНА --}}
            <div class="p-4 border border-destructive/30 rounded-lg bg-destructive/5 h-full">
                <h3 class="text-sm font-semibold mb-3 text-destructive flex items-center gap-2">
                    <x-lucide-shield-x class="w-4 h-4" /> Заблокировать пользователя
                </h3>
                
                <div class="space-y-3">
                    {{-- Селекты в один ряд --}}
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.select wire:model.live="ban_reason" class="w-full">
                            <x-ui.select-trigger><x-ui.select-value placeholder="Причина" /></x-ui.select-trigger>
                            <x-ui.select-content>
                                @foreach($banReasons as $value => $label)
                                    <x-ui.select-item value="{{ $value }}" wire:key="reason-{{ $value }}">{{ $label }}</x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>

                        <x-ui.select wire:model.live="ban_type" class="w-full">
                            <x-ui.select-trigger><x-ui.select-value placeholder="Тип" /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="permanent">Вечный</x-ui.select-item>
                                <x-ui.select-item value="temp">На 3 дня</x-ui.select-item>
                                <x-ui.select-item value="shadow">Теневой</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>

                    <x-ui.button wire:click="applyBan" wire:confirm="Вы уверены, что хотите заблокировать этого пользователя?" variant="destructive" class="w-full">
                        <x-lucide-ban class="w-4 h-4" /> Применить блокировку
                    </x-ui.button>
                </div>
            </div>
        @else
            {{-- КНОПКА РАЗБАНА --}}
            <div class="p-4 border border-green-500/30 rounded-lg bg-green-500/5">
                <h3 class="text-sm font-semibold mb-3 text-green-600 flex items-center gap-2">
                    <x-lucide-shield-check class="w-4 h-4" /> Снять ограничения
                </h3>
                <p class="text-xs text-muted-foreground mb-3">
                    Полностью снять бан/теневой бан и восстановить аккаунт. Пользователь снова сможет заходить в приложение и отображаться в ленте.
                </p>
                <x-ui.button wire:click="unbanUser" wire:confirm="Разбанить пользователя?" variant="success" class="w-full">
                    <x-lucide-unlock class="w-4 h-4" /> Разбанить
                </x-ui.button>
            </div>
        @endif

    </div>

    {{-- 3. ИСТОРИЯ ДЕЙСТВИЙ (ЖУРНАЛ) --}}
    @if(!empty($banHistory))
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <h3 class="text-sm font-semibold mb-4 flex items-center gap-2">
                <x-lucide-history class="w-4 h-4" /> История блокировок
            </h3>
            
            <div class="space-y-4 relative before:absolute before:left-[15px] before:top-2 before:bottom-2 before:w-px before:bg-border">
                @foreach($banHistory as $log)
                    @php 
                        // Анализируем дифф, чтобы понять, какой именно бан был применен в тот момент
                        $titleText = 'Действие';
                        $badgeConfig = ['variant' => 'secondary', 'label' => 'Действие'];
                        $banPeriodText = ''; // Переменная для текста периода

                        if ($log->action === 'user.unban') {
                            $titleText = 'Разблокирован';
                            $badgeConfig = ['variant' => 'success', 'label' => 'Разбан'];
                        } elseif (in_array($log->action, ['user.delete', 'user.mass_delete'])) {
                            $titleText = 'Деактивирован';
                            $badgeConfig = ['variant' => 'secondary', 'label' => 'Удален'];
                        } else { // user.ban, user.mass_ban, user.shadowban
                            $afterStatus = $log->after['status'] ?? null;
                            $hasUntil = !empty($log->after['banned_until']);

                            if ($afterStatus === 'shadowbanned') {
                                $titleText = 'Теневой бан';
                                $badgeConfig = ['variant' => 'warning', 'label' => 'Теневой'];
                            } elseif ($hasUntil) {
                                $titleText = 'Временный бан';
                                
                                // Динамически считаем количество дней бана
                                $startDate = \Carbon\Carbon::parse($log->created_at);
                                $endDate = \Carbon\Carbon::parse($log->after['banned_until']);
                                $days = $startDate->diffInDays($endDate);
                                
                                $badgeConfig = ['variant' => 'destructive', 'label' => "На {$days} дн."];
                                
                                // Формируем текст для периода
                                $banPeriodText = "С: <span class='text-foreground'>{$startDate->format('d.m.Y H:i')}</span><br>По: <span class='text-foreground'>{$endDate->format('d.m.Y H:i')}</span>";
                            } else {
                                $titleText = 'Заблокирован';
                                $badgeConfig = ['variant' => 'destructive', 'label' => 'Вечный'];
                            }
                        }
                    @endphp
                    <div class="flex gap-4 items-start relative">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 shrink-0 
                            {{ $log->action === 'user.unban' ? 'bg-green-500/10 text-green-500' : 'bg-destructive/10 text-destructive' }}">
                            @if($log->action === 'user.unban')
                                <x-lucide-unlock class="w-4 h-4" />
                            @else
                                <x-lucide-ban class="w-4 h-4" />
                            @endif
                        </div>
                        
                        <div class="flex-1 pt-1">
                            <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">{{ $titleText }}</span>
                                    <x-ui.badge variant="{{ $badgeConfig['variant'] }}" size="xs">{{ $badgeConfig['label'] }}</x-ui.badge>
                                </div>
                                <span class="text-[10px] text-muted-foreground">{{ $log->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                            
                            <div class="text-xs text-muted-foreground">
                                @if(!empty($log->after['ban_reason']))
                                    Причина: <span class="text-foreground">{{ $log->after['ban_reason'] }}</span><br>
                                @endif
                                
                                @if(!empty($banPeriodText))
                                    {!! $banPeriodText !!}<br>
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
    @endif
</div>