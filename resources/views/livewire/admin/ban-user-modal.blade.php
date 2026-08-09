<?php

use App\Actions\Admin\ToggleUserBanAction;
use App\Enums\BanReason;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public bool $isVisible = false;
    public array $userIds = []; // Теперь всегда массив
    public bool $isMass = false;
    public ?string $userName = null;
    public ?string $banType = null;
    
    public string $banReason = 'other';

    public function boot(ToggleUserBanAction $toggleUserBanAction): void
    {
        $this->toggleUserBanAction = $toggleUserBanAction;
    }

       #[On('open-ban-modal')]
    public function open(array $userIds, string $banType): void
    {
        // Полная амнезия
        $this->reset(['userIds', 'isMass', 'userName', 'banType', 'banReason']);

        // Приводим к массиву (на случай одиночного бана)
        $this->userIds = $userIds;
        $this->banType = $banType;
        $this->banReason = 'other';

        if (count($this->userIds) > 1) {
            $this->isMass = true;
            $this->userName = 'Выбрано ' . count($this->userIds) . ' пользователей';
        } else {
            $user = User::find($this->userIds[0]);
            if (!$user) {
                $this->dispatch('show-toast', type: 'error', message: 'Пользователь не найден');
                return;
            }
            $this->userName = $user->name;
        }
        
        $this->isVisible = true;
    }

    public function close(): void
    {
        $this->isVisible = false;
    }

    public function getBanTypeLabelProperty(): string
    {
        return match($this->banType) {
            'shadow' => 'Теневой бан',
            'temp' => 'Бан на 3 дня',
            'permanent' => 'Вечный бан',
            default => 'Бан'
        };
    }

    public function executeBan(): void
    {
        if (empty($this->userIds) || !$this->banType) return;

        $reasonEnum = BanReason::tryFrom($this->banReason);
        $reasonLabel = $reasonEnum ? $reasonEnum->label() : 'Нарушение правил сервиса';

        $bannedCount = 0;
        foreach ($this->userIds as $id) {
            $user = User::find($id);
            if ($user) {
                // Вызываем с $forceBan = true, чтобы случайно не разбанить
                $result = $this->toggleUserBanAction->execute($user, $reasonLabel, $this->banType, true);
                if ($result['success']) $bannedCount++;
            }
        }

        $message = $this->isMass ? "Забанено {$bannedCount} из " . count($this->userIds) . " пользователей" : $result['message'] ?? 'Готово';
        
        $this->dispatch('show-toast', type: 'success', message: $message);
        $this->dispatch('user-action-performed');

        $this->close();
    }
};
?>

<div x-data="{ open: @entangle('isVisible') }" 
     x-show="open" 
     x-cloak
     x-transition.opacity
     @click.self="open = false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
     
    <div x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="bg-card rounded-lg shadow-xl w-full max-w-md p-6 space-y-5 border border-border">
        
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-foreground flex items-center gap-2">
                    <x-lucide-shield-alert class="w-5 h-5 text-red-500" />
                    {{ $this->banTypeLabel }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">
                    Вы собираетесь забанить: <span class="font-bold text-foreground">{{ $userName }}</span>
                </p>
            </div>
            <x-ui.button variant="ghost" size="icon-sm" @click="open = false">
                <x-lucide-x class="w-5 h-5" />
            </x-ui.button>
        </div>

        <div class="space-y-4">
            <div>
                <label class="text-xs font-medium text-muted-foreground mb-2 block uppercase tracking-wider">Причина блокировки</label>
                <x-ui.select wire:model="banReason" wire:key="ban-reason-select-{{ implode('-', $userIds) }}">
                    <x-ui.select-trigger class="w-full"><x-ui.select-value placeholder="Выберите причину" /></x-ui.select-trigger>
                    <x-ui.select-content>
                        @foreach (\App\Enums\BanReason::options() as $value => $label)
                            <x-ui.select-item value="{{ $value }}">{{ $label }}</x-ui.select-item>
                        @endforeach
                    </x-ui.select-content>
                </x-ui.select>
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <x-ui.button @click="open = false" variant="ghost" size="sm">Отмена</x-ui.button>
            <x-ui.button wire:click="executeBan" variant="destructive" size="sm" wire:loading.attr="disabled" class="gap-2">
                <x-lucide-loader-circle class="w-4 h-4 animate-spin" wire:loading />
                <x-lucide-lock class="w-4 h-4" wire:loading.remove />
                <span wire:loading.remove>Подтвердить</span>
                <span wire:loading>Баним...</span>
            </x-ui.button>
        </div>
    </div>
</div>