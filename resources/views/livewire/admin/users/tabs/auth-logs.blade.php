<?php

use App\Models\User;
use App\Models\UserAuthLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    // НОВОЕ: Добавлено для консистентности с другими табами
    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    #[Computed]
    public function logs()
    {
        return UserAuthLog::where('user_id', $this->userId)
            ->latest()
            ->paginate(15);
    }

    #[On('user-action-performed')] 
    public function refreshLogs(): void
    {
        unset($this->logs);
    }
}; 
?>

<div class="space-y-4">
    @if($this->logs->isEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-dashed border-border text-center text-xs text-muted-foreground">
            История входов пуста.
        </div>
    @else
        <x-ui.table>
            <x-ui.table-header>
                <x-ui.table-row>
                    <x-ui.table-head>IP Адрес</x-ui.table-head>
                    <x-ui.table-head>Устройство</x-ui.table-head>
                    <x-ui.table-head>Тип</x-ui.table-head>
                    <x-ui.table-head>Статус</x-ui.table-head>
                    <x-ui.table-head>Дата входа</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>
            <x-ui.table-body>
                @foreach($this->logs as $log)
                    <x-ui.table-row wire:key="log-{{ $log->id }}">
                        <x-ui.table-cell>
                            <span class="text-sm font-mono">{{ $log->ip_address }}</span>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center gap-2">
                                @if($log->device_type === 'mobile')
                                    <x-lucide-smartphone class="w-4 h-4 text-muted-foreground" />
                                @else
                                    <x-lucide-monitor class="w-4 h-4 text-muted-foreground" />
                                @endif
                                <span class="text-sm">{{ $log->device_os ?? 'Неизвестно' }}</span>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-xs text-muted-foreground">
                            {{ $log->device_type ?? '—' }}
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            @if($log->is_successful)
                                <x-ui.badge variant="success" size="xs">Успешно</x-ui.badge>
                            @else
                                <x-ui.badge variant="destructive" size="xs">Ошибка</x-ui.badge>
                            @endif
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                            {{ $log->created_at->format('d.m.Y H:i') }}
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforeach
            </x-ui.table-body>
        </x-ui.table>
        <div class="mt-2">{{ $this->logs->links('partials.pagination') }}</div>
    @endif
</div>