<?php

use App\Actions\Admin\DeleteUserAction;
use App\Enums\DeletionReason;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public bool $isVisible = false;
    public ?int $userId = null;
    public ?string $userName = null;
    
    // Дефолтное значение
    public string $deleteReason = 'other';

    public function boot(DeleteUserAction $deleteUserAction): void
    {
        $this->deleteUserAction = $deleteUserAction;
    }

    #[On('open-delete-modal')]
    public function open(int $userId): void
    {
        // Полная амнезия
        $this->reset(['userId', 'userName', 'deleteReason']);

        $user = User::find($userId);
        if (!$user) {
            $this->dispatch('show-toast', type: 'error', message: 'Пользователь не найден');
            return;
        }

        $this->userId = (int) $userId;
        $this->userName = $user->name;
        
        $this->isVisible = true;
    }

    public function close(): void
    {
        $this->isVisible = false;
    }

    public function executeDelete(): void
    {
        if (!$this->userId) return;

        $user = User::find($this->userId);
        if (!$user) {
            $this->dispatch('show-toast', type: 'error', message: 'Пользователь не найден');
            return;
        }

        // Конвертируем Enum в красивый лейбл для лога
        $reasonEnum = DeletionReason::tryFrom($this->deleteReason);
        $reasonLabel = $reasonEnum ? $reasonEnum->label() : 'Причина не указана';

        $this->deleteUserAction->execute($user, auth()->user(), $reasonLabel);
        
        $this->dispatch('show-toast', type: 'success', message: "Юзер ID {$this->userId} удален");
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
                    <x-lucide-trash-2 class="w-5 h-5 text-red-500" />
                    Удаление пользователя
                </h2>
                <p class="text-sm text-muted-foreground mt-1">
                    Вы собираетесь удалить (деактивировать): <span class="font-bold text-foreground">{{ $userName }}</span>
                </p>
            </div>
            <x-ui.button variant="ghost" size="icon-sm" @click="open = false">
                <x-lucide-x class="w-5 h-5" />
            </x-ui.button>
        </div>

        <div class="space-y-4">
            <div>
                <label class="text-xs font-medium text-muted-foreground mb-2 block uppercase tracking-wider">Причина удаления</label>
                <x-ui.select wire:model="deleteReason" wire:key="delete-reason-select-{{ $userId }}">
                    <x-ui.select-trigger class="w-full"><x-ui.select-value placeholder="Выберите причину" /></x-ui.select-trigger>
                    <x-ui.select-content>
                        @foreach (\App\Enums\DeletionReason::options() as $value => $label)
                            <x-ui.select-item value="{{ $value }}">{{ $label }}</x-ui.select-item>
                        @endforeach
                    </x-ui.select-content>
                </x-ui.select>
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <x-ui.button @click="open = false" variant="ghost" size="sm">Отмена</x-ui.button>
            <x-ui.button wire:click="executeDelete" variant="destructive" size="sm" wire:loading.attr="disabled" class="gap-2">
                <x-lucide-loader-circle class="w-4 h-4 animate-spin" wire:loading />
                <x-lucide-trash-2 class="w-4 h-4" wire:loading.remove />
                <span wire:loading.remove>Подтвердить удаление</span>
                <span wire:loading>Удаляем...</span>
            </x-ui.button>
        </div>
    </div>
</div>