<?php

use App\Models\User;
use App\Models\AdminLog;
use Livewire\Volt\Component;

new class extends Component 
{
    public User $user;

    // Свойства для формы бана
    public string $ban_reason = '';
    public ?string $ban_duration = null; // null = вечный, либо кол-во дней

    public function banUser(): void
    {
        $this->validate([
            'ban_reason' => 'required|string|min:3',
        ]);

        $bannedUntil = $this->ban_duration ? now()->addDays((int) $this->ban_duration) : null;

        $this->user->update([
            'status' => 'banned',
            'ban_reason' => $this->ban_reason,
            'banned_until' => $bannedUntil,
        ]);

        AdminLog::record('user.ban', $this->user, auth()->user(), 
            ['status' => 'active'], 
            ['status' => 'banned', 'reason' => $this->ban_reason, 'until' => $bannedUntil]
        );

        $this->reset('ban_reason', 'ban_duration');
        $this->dispatch('show-toast', type: 'success', message: 'Пользователь забанен');
        $this->dispatch('user-updated')->to('admin.users.show'); // Обновляем шапку
    }

    public function shadowbanUser(): void
    {
        $this->user->update(['status' => 'shadowbanned']);

        AdminLog::record('user.shadowban', $this->user, auth()->user(), 
            ['status' => 'active'], 
            ['status' => 'shadowbanned']
        );

        $this->dispatch('show-toast', type: 'success', message: 'Теневой бан активирован');
        $this->dispatch('user-updated')->to('admin.users.show');
    }

    public function unbanUser(): void
    {
        $oldStatus = $this->user->status;
        
        $this->user->update([
            'status' => 'active',
            'ban_reason' => null,
            'banned_until' => null,
        ]);

        AdminLog::record('user.unban', $this->user, auth()->user(), 
            ['status' => $oldStatus], 
            ['status' => 'active']
        );

        $this->dispatch('show-toast', type: 'success', message: 'Пользователь разбанен');
        $this->dispatch('user-updated')->to('admin.users.show');
    }
}; 
?>

<div class="space-y-6">
    {{-- Текущий статус --}}
    <div class="p-4 bg-muted/20 rounded-lg border border-border">
        <h3 class="text-sm font-semibold mb-3">Текущий статус аккаунта</h3>
        
        <div class="flex items-center gap-4">
            @php 
                $statusConfig = match($user->status) {
                    'active' => ['variant' => 'success', 'label' => 'Активен', 'icon' => 'check-circle'],
                    'banned' => ['variant' => 'destructive', 'label' => 'Забанен', 'icon' => 'ban'],
                    'shadowbanned' => ['variant' => 'warning', 'label' => 'Теневой бан', 'icon' => 'eye-off'],
                    default => ['variant' => 'secondary', 'label' => $user->status, 'icon' => 'help-circle']
                };
            @endphp

            <x-ui.badge variant="{{ $statusConfig['variant'] }}" size="lg">
                <x-dynamic-component component="lucide-{{ $statusConfig['icon'] }}" class="w-4 h-4 inline mr-1" />
                {{ $statusConfig['label'] }}
            </x-ui.badge>

            @if($user->status === 'banned')
                <div class="text-sm text-muted-foreground">
                    Причина: <span class="text-foreground font-medium">{{ $user->ban_reason }}</span>
                    @if($user->banned_until)
                        <br>До: {{ $user->banned_until->format('d.m.Y H:i') }}
                    @else
                        <br><span class="text-destructive font-medium">Бессрочно</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Действия --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        {{-- Форма бана --}}
        @if($user->status !== 'banned')
            <div class="p-4 border border-destructive/30 rounded-lg bg-destructive/5">
                <h3 class="text-sm font-semibold mb-3 text-destructive">Забанить пользователя</h3>
                
                <div class="space-y-3">
                    <x-ui.input wire:model="ban_reason" placeholder="Причина бана (обязательно)" />
                    
                    <select wire:model="ban_duration" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                        <option value="">Навсегда</option>
                        <option value="1">На 1 день</option>
                        <option value="7">На 7 дней</option>
                        <option value="30">На 30 дней</option>
                    </select>

                    <x-ui.button wire:click="banUser" wire:confirm="Вы уверены, что хотите забанить?" variant="destructive" class="w-full">
                        <x-lucide-ban class="w-4 h-4" /> Забанить
                    </x-ui.button>
                </div>
            </div>
        @endif

        {{-- Теневой бан / Разбан --}}
        <div class="space-y-4">
            @if($user->status === 'active')
                <div class="p-4 border border-yellow-500/30 rounded-lg bg-yellow-500/5">
                    <h3 class="text-sm font-semibold mb-3 text-yellow-600">Теневой бан</h3>
                    <p class="text-xs text-muted-foreground mb-3">Пользователь сможет заходить в приложение, но его анкета не будет показываться другим юзерам в ленте.</p>
                    <x-ui.button wire:click="shadowbanUser" wire:confirm="Включить теневой бан?" variant="warning" class="w-full">
                        <x-lucide-eye-off class="w-4 h-4" /> Теневой бан
                    </x-ui.button>
                </div>
            @endif

            @if($user->status !== 'active')
                <div class="p-4 border border-green-500/30 rounded-lg bg-green-500/5">
                    <h3 class="text-sm font-semibold mb-3 text-green-600">Снять ограничения</h3>
                    <p class="text-xs text-muted-foreground mb-3">Полностью снять бан/теневой бан и восстановить аккаунт.</p>
                    <x-ui.button wire:click="unbanUser" wire:confirm="Разбанить пользователя?" variant="success" class="w-full">
                        <x-lucide-unlock class="w-4 h-4" /> Разбанить
                    </x-ui.button>
                </div>
            @endif
        </div>
    </div>
</div>