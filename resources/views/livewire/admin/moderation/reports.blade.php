<?php

use App\Models\Report;
use App\Models\User;
use App\Models\AdminLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'status', except: 'pending')]
    public string $statusFilter = 'pending';
    
    #[Url(as: 'type', except: 'all')]
    public string $typeFilter = 'all'; // all, user, photo, comment

    public string $search = '';
    public int $perPage = 10;

    // Состояние модалки решения
    public ?int $resolvingReportId = null;
    public string $resolution = 'ban'; // ban, warn, no_action
    public string $resolutionNote = '';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    // === ДЕЙСТВИЯ ===

    public function openResolveModal(int $reportId, string $resolution = 'ban'): void
    {
        $this->resolvingReportId = $reportId;
        $this->resolution = $resolution;
        $this->resolutionNote = '';
    }

    public function resolveReport(): void
    {
        $this->validate(['resolutionNote' => 'nullable|string']);

        $report = Report::find($this->resolvingReportId);
        if (!$report || $report->status !== 'open') return;

        // Решаем жалобу через наш новый метод
        $report->resolve(auth()->id(), $this->resolution, $this->resolutionNote);

        // Если решение "Бан" — баним юзера
        if ($this->resolution === 'ban' && $report->reported_id) {
            $user = User::find($report->reported_id);
            if ($user && $user->status === 'active') {
                $user->update([
                    'status' => 'banned',
                    'ban_reason' => $this->resolutionNote ?: 'Нарушение по жалобе пользователей'
                ]);
                AdminLog::record('user.ban', $user, auth()->user());
            }
        }

        // Логируем решение по жалобе
        AdminLog::record('report.resolve', $report, auth()->user(), ['status' => 'open'], ['status' => 'resolved', 'resolution' => $this->resolution]);

        $this->resolvingReportId = null;
        $this->dispatch('show-toast', type: 'success', message: 'Жалоба разрешена');
    }

    public function markFalsePositive(int $reportId): void
    {
        $report = Report::find($reportId);
        if (!$report) return;

        $report->markAsFalsePositive(auth()->id());
        AdminLog::record('report.false_positive', $report, auth()->user());

        $this->dispatch('show-toast', type: 'info', message: 'Жалоба отклонена (ложная тревога)');
    }

    // === ВЫВОД ДАННЫХ ===

    public function with(): array
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        $reports = Report::with(['reporter', 'reported', 'reportable'])
            ->whereHas('reporter', fn($q) => $q->excludeStaff())
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', function ($q) {
                // Фильтр по типу цели (полиморфно)
                $typeMap = [
                    'user' => \App\Models\User::class,
                    'photo' => \App\Models\Photo::class,
                    'comment' => \App\Models\PhotoComment::class,
                ];
                if (isset($typeMap[$this->typeFilter])) {
                    $q->where('reportable_type', $typeMap[$this->typeFilter]);
                }
            })
            ->when($this->search, function ($q) use ($operator) {
                $q->where('reason', $operator, "%{$this->search}%")
                  ->orWhereHas('reporter', fn($sub) => $sub->where('name', $operator, "%{$this->search}%"))
                  ->orWhereHas('reported', fn($sub) => $sub->where('name', $operator, "%{$this->search}%"));
            })
            ->latest()
            ->paginate($this->perPage);

        $counts = Report::selectRaw("
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'false_positive' THEN 1 ELSE 0 END) as false_positive,
            COUNT(*) as total
        ")->first();

        return [
            'reports' => $reports,
            'openCount' => (int) ($counts->open ?? 0),
            'resolvedCount' => (int) ($counts->resolved ?? 0),
            'falsePositiveCount' => (int) ($counts->false_positive ?? 0),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold">Жалобы</h1>
        @if ($openCount > 0)
            <span class="bg-destructive/10 text-destructive px-3 py-1 rounded-full text-sm font-medium">
                В очереди: {{ $openCount }}
            </span>
        @endif
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-2">
            <x-ui.button wire:click="$set('statusFilter', 'open')" variant="{{ $statusFilter == 'open' ? 'default' : 'secondary' }}">
                Ожидают <x-ui.badge>{{ $openCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'resolved')" variant="{{ $statusFilter == 'resolved' ? 'default' : 'secondary' }}">
                Решены <x-ui.badge>{{ $resolvedCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'false_positive')" variant="{{ $statusFilter == 'false_positive' ? 'default' : 'secondary' }}">
                Ложные <x-ui.badge>{{ $falsePositiveCount }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="h-6 w-px bg-border"></div>

        <!-- Фильтр по типу жалобы -->
        <select wire:model.live="typeFilter" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm md:w-[160px]">
            <option value="all">Все типы</option>
            <option value="user">На юзеров</option>
            <option value="photo">На фото</option>
            <option value="comment">На комментарии</option>
        </select>

        <div class="ml-auto relative">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Поиск по имени или причине..."
                class="pl-9 pr-3 py-2 text-sm bg-card border border-border rounded-lg focus:outline-none w-64" />
        </div>
    </div>

    <!-- Таблица жалоб -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head>Жалобщик</x-ui.table-head>
                <x-ui.table-head>Объект жалобы</x-ui.table-head>
                <x-ui.table-head>Причина</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($reports as $report)
                <x-ui.table-row wire:key="report-{{ $report->id }}">
                    <x-ui.table-cell class="text-muted-foreground text-xs">{{ $report->id }}</x-ui.table-cell>
                    
                    <!-- Жалобщик -->
                    <x-ui.table-cell>
                        <a href="{{ route('admin.users.show', $report->reporter?->id) }}" wire:navigate class="flex items-center gap-2 group">
                            <x-avatar src="{{ $report->reporter?->avatar_url }}" name="{{ $report->reporter?->name }}" size="sm" />
                            <span class="text-sm font-medium group-hover:text-primary">{{ $report->reporter?->name ?? 'Удален' }}</span>
                        </a>
                    </x-ui.table-cell>

                    <!-- Объект жалобы (Полиморфный вывод) -->
                    <x-ui.table-cell>
                        @if($report->reportable_type === \App\Models\User::class)
                            <a href="{{ route('admin.users.show', $report->reportable_id) }}" wire:navigate class="flex items-center gap-2 group">
                                <x-avatar src="{{ $report->reportable?->avatar_url }}" name="{{ $report->reportable?->name }}" size="sm" />
                                <div>
                                    <span class="text-sm font-medium group-hover:text-primary">{{ $report->reportable?->name ?? 'Удален' }}</span>
                                    <x-ui.badge variant="warning" size="xs" class="ml-1">Юзер</x-ui.badge>
                                    @if($report->reportable?->status === 'banned') <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge> @endif
                                </div>
                            </a>
                        @elseif($report->reportable_type === \App\Models\Photo::class)
                            <div class="flex items-center gap-2">
                                <img src="{{ $report->reportable?->thumb_url }}" class="w-10 h-10 object-cover rounded">
                                <div>
                                    <span class="text-sm">Фото #{{ $report->reportable_id }}</span>
                                    <x-ui.badge variant="secondary" size="xs" class="ml-1">Фото</x-ui.badge>
                                </div>
                            </div>
                        @elseif($report->reportable_type === \App\Models\PhotoComment::class)
                            <div>
                                <span class="text-sm">Комментарий #{{ $report->reportable_id }}</span>
                                <x-ui.badge variant="secondary" size="xs" class="ml-1">Коммент</x-ui.badge>
                                <p class="text-xs text-muted-foreground truncate max-w-[200px]">{{ Str::limit($report->reportable?->content, 30) }}</p>
                            </div>
                        @else
                            <span class="text-sm text-muted-foreground">Удалено</span>
                        @endif
                    </x-ui.table-cell>

                    <!-- Причина -->
                    <x-ui.table-cell class="max-w-[25rem]">
                        <p class="text-sm line-clamp-2">{{ $report->reason }}</p>
                        @if($report->description)
                            <p class="text-xs text-muted-foreground mt-1 line-clamp-1">{{ $report->description }}</p>
                        @endif
                    </x-ui.table-cell>

                    <!-- Статус -->
                    <x-ui.table-cell>
                        @php $badge = $report->status_badge; @endphp
                        <x-ui.badge variant="{{ $badge['variant'] }}" size="sm">{{ $badge['label'] }}</x-ui.badge>
                    </x-ui.table-cell>

                    <!-- Дата -->
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $report->created_at->format('d.m.Y') }}
                    </x-ui.table-cell>

                    <!-- Действия -->
                    <x-ui.table-cell class="text-right">
                        @if($report->status === 'open')
                            <x-ui.dropdown-menu>
                                <x-ui.dropdown-menu-trigger>
                                    <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                                </x-ui.dropdown-menu-trigger>
                                <x-ui.dropdown-menu-content align="end">
                                    <x-ui.dropdown-menu-item wire:click="openResolveModal({{ $report->id }}, 'ban')">
                                        <x-lucide-ban class="w-4 h-4 text-destructive" /> Забанить и закрыть
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item wire:click="openResolveModal({{ $report->id }}, 'warn')">
                                        <x-lucide-alert-triangle class="w-4 h-4 text-yellow-500" /> Предупредить и закрыть
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item wire:click="openResolveModal({{ $report->id }}, 'no_action')">
                                        <x-lucide-check-circle class="w-4 h-4 text-green-500" /> Нет нарушения
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-separator />
                                    <x-ui.dropdown-menu-item wire:click="markFalsePositive({{ $report->id }})">
                                        <x-lucide-x-circle class="w-4 h-4" /> Ложная тревога
                                    </x-ui.dropdown-menu-item>
                                </x-ui.dropdown-menu-content>
                            </x-ui.dropdown-menu>
                        @else
                            <span class="text-xs text-muted-foreground">{{ $report->resolution ?? '—' }}</span>
                        @endif
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="7" class="py-12 text-center text-muted-foreground">
                        <x-lucide-inbox class="w-12 h-12 mx-auto opacity-30 mb-2" />
                        <p>Нет жалоб</p>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <div class="mt-4">
        {{ $reports->links('partials.pagination') }}
    </div>

    <!-- МОДАЛКА РЕШЕНИЯ ЖАЛОБЫ -->
    <div x-data="{ show: @entangle('resolvingReportId') }" x-show="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
        <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md shadow-xl">
            <h3 class="text-lg font-semibold mb-4">Разрешить жалобу</h3>
            
            <div class="space-y-3 mb-4">
                <p class="text-sm text-muted-foreground">
                    Действие: <span class="text-foreground font-medium">{{ ucfirst($this->resolution) }}</span>
                </p>

                <div>
                    <label class="text-sm font-medium">Комментарий модератора (для логов)</label>
                    <textarea wire:model="resolutionNote" rows="3" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button wire:click="$set('resolvingReportId', null)">Отмена</x-ui.button>
                <x-ui.button wire:click="resolveReport" variant="default">Принять решение</x-ui.button>
            </div>
        </div>
    </div>
</div>