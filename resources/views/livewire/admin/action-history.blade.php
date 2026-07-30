<?php

use Livewire\Volt\Component;
use App\Models\ModerationLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // Фильтры
    public ?string $filterAction = null;

    public function updatingFilterAction(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function logs()
    {
        return ModerationLog::with(['admin', 'user'])
            ->when($this->filterAction, fn($q) => $q->where('action', $this->filterAction))
            ->latest()
            ->paginate(30);
    }

    // Хелпер для красивого перевода действий
    public function getActionLabel(string $action): string
    {
        return match($action) {
            'photo_deleted' => '🗑️ Удаление фото',
            'user_banned' => '🚫 Бан',
            'user_unbanned' => '✅ Разбан',
            'shadowban_enabled' => '👁️‍🗨️ Теневой бан',
            'shadowban_disabled' => '👁️ Снятие ТБ',
            default => $action,
        };
    }

    // Хелпер для цвета бейджа
    public function getActionColor(string $action): string
    {
        return match($action) {
            'photo_deleted' => 'destructive',
            'user_banned', 'shadowban_enabled' => 'warning',
            'user_unbanned', 'shadowban_disabled' => 'success',
            default => 'secondary',
        };
    }
}; 
?>

<div>
    <!-- Шапка -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <a href="#" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold">История действий</h1>
                <p class="text-sm text-muted-foreground">Лог модерации и действий администраторов</p>
            </div>
        </div>

        <!-- Фильтр -->
        <div class="flex items-center gap-2">
            <x-ui.select wire:model.live="filterAction" class="w-48">
                <option value="">Все действия</option>
                <option value="photo_deleted">Удаление фото</option>
                <option value="user_banned">Баны</option>
                <option value="shadowban_enabled">Теневые баны</option>
            </x-ui.select>
        </div>
    </div>

    <!-- Таблица -->
    <div class="mt-6 bg-card border border-border rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Дата</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Админ</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Пользователь</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Действие</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Объект</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Детали</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($this->logs as $log)
                        <tr class="hover:bg-muted/10 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p class="font-medium">{{ $log->created_at->format('d.m.Y') }}</p>
                                <p class="text-xs text-muted-foreground">{{ $log->created_at->format('H:i') }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-lucide-shield class="w-4 h-4 text-primary" />
                                    <span class="font-medium">{{ $log->admin?->name ?? 'Система' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.users.show', $log->user_id) }}" wire:navigate class="text-primary hover:underline">
                                    {{ $log->user?->name ?? 'Удален' }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="{{ $this->getActionColor($log->action) }}" size="xs">
                                    {{ $this->getActionLabel($log->action) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                @if($log->subject_type === 'Photo')
                                    Фото #{{ $log->subject_id }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 max-w-xs truncate">
                                @if($log->metadata)
                                    <button x-data="{ open: false }" @click="open = !open" class="text-xs text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1">
                                        <x-lucide-eye class="w-3 h-3" /> Просмотр
                                    </button>
                                    <div x-show="open" x-transition class="mt-2 p-2 bg-muted/30 rounded text-xs font-mono whitespace-pre-wrap break-all">
                                        @json($log->metadata)
                                    </div>
                                @elseif($log->reason)
                                    <span class="text-xs text-muted-foreground">{{ Str::limit($log->reason, 50) }}</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">
                                <x-lucide-inbox class="w-12 h-12 mx-auto mb-3 opacity-30" />
                                <p>Записей пока нет</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Пагинация -->
        <div class="p-4 border-t border-border">
            {{ $this->logs->links() }}
        </div>
    </div>
</div>