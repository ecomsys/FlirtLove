<?php

use App\Models\Broadcast;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Управление оповещениями (Admin)
|--------------------------------------------------------------------------
| Компонент для создания, редактирования, отправки и управления
| системными оповещениями пользователей.
*/
new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    // Фильтры и поиск
    public string $search = '';
    public string $statusFilter = 'all';
    public string $typeFilter = 'all';
    public int $perPage = 10;

    // Поля формы создания/редактирования
    public string $modalTitle = '';
    public string $modalType = 'system';
    public string $modalMessage = '';
    public ?int $selectedUserId = null;
    public string $scheduledDate = '';
    public bool $showCreateModal = false;

    // Редактирование
    public ?int $editingId = null;
    public bool $showEditModal = false;

    /**
     * Сброс пагинации при обновлении поиска
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Сброс пагинации при смене фильтра статуса
     */
    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Сброс пагинации при смене фильтра типа
     */
    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Установить фильтр по статусу
     */
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    /**
     * Установить фильтр по типу
     */
    public function setTypeFilter(string $type): void
    {
        $this->typeFilter = $type;
        $this->resetPage();
    }

    /**
     * Открыть модалку создания оповещения
     */
    public function createModal(): void
    {
        $this->reset(['modalTitle', 'modalType', 'modalMessage', 'selectedUserId', 'scheduledDate', 'editingId']);
        $this->showCreateModal = true;
    }

    /**
     * Открыть модалку редактирования оповещения
     */
    public function editModal(int $id): void
    {
        $broadcast = Broadcast::find($id);
        if ($broadcast) {
            $this->editingId = $id;
            $this->modalTitle = $broadcast->title;
            $this->modalType = $broadcast->type;
            $this->modalMessage = $broadcast->message;
            $this->selectedUserId = $broadcast->user_id;
            $this->scheduledDate = $broadcast->scheduled_at?->format('Y-m-d\TH:i') ?? '';
            
            $this->dispatch('open-edit-modal');
        }
    }

    /**
     * Обновить существующее оповещение
     */
    public function updateBroadcast(): void
    {
        $this->validate([
            'modalTitle' => 'required|string|max:255',
            'modalMessage' => 'required|string|max:5000',
            'modalType' => 'required|in:system,email,push',
            'scheduledDate' => 'nullable|date|after_or_equal:now',
        ]);

        $broadcast = Broadcast::find($this->editingId);
        if ($broadcast) {
            $currentStatus = $broadcast->status;
            
            // Определяем новый статус
            if ($this->scheduledDate) {
                $status = 'scheduled';
            } elseif ($currentStatus === 'draft') {
                $status = 'draft';
            } else {
                $status = 'sent';
            }

            $broadcast->update([
                'user_id' => $this->selectedUserId,
                'type' => $this->modalType,
                'title' => $this->modalTitle,
                'message' => $this->modalMessage,
                'status' => $status,
                'scheduled_at' => $this->scheduledDate ?: null,
                'sent_at' => $status === 'sent' ? now() : ($status === 'draft' ? null : $broadcast->sent_at),
            ]);

            $this->dispatch('show-toast', type: 'success', message: 'Оповещение обновлено!');
            $this->showEditModal = false;
            $this->reset(['modalTitle', 'modalMessage', 'selectedUserId', 'scheduledDate', 'editingId']);
            $this->dispatch('$refresh');
        }
    }

    /**
     * Создать и отправить новое оповещение
     */
    public function sendBroadcast(): void
    {
        $this->validate([
            'modalTitle' => 'required|string|max:255',
            'modalMessage' => 'required|string|max:5000',
            'modalType' => 'required|in:system,email,push',
            'scheduledDate' => 'nullable|date|after_or_equal:now',
        ]);

        $status = $this->scheduledDate ? 'scheduled' : 'sent';
        $broadcast = Broadcast::create([
            'user_id' => $this->selectedUserId,
            'type' => $this->modalType,
            'title' => $this->modalTitle,
            'message' => $this->modalMessage,
            'status' => $status,
            'scheduled_at' => $this->scheduledDate ?: null,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);

        if ($status === 'sent') {
            $this->sendRealBroadcast($broadcast);
        }

        $this->dispatch('show-toast', type: 'success', message: $status === 'sent' ? 'Оповещение отправлено!' : 'Оповещение запланировано!');
        $this->showCreateModal = false;
        $this->reset(['modalTitle', 'modalMessage', 'selectedUserId', 'scheduledDate']);
        $this->dispatch('$refresh');
    }

    /**
     * Реальная отправка оповещения (Email, Push, System)
     * TODO: Реализовать логику отправки в зависимости от типа
     */
    private function sendRealBroadcast($broadcast): void
    {
        if ($broadcast->type === 'system') {
            // Сохраняем в БД для отображения в интерфейсе пользователя
            // Можно создать модель UserBroadcast или использовать существующую таблицу
        }
        
        if ($broadcast->type === 'email') {
            // Отправка email
            // Mail::to($user->email)->send(new BroadcastMail($broadcast));
        }
        
        if ($broadcast->type === 'push') {
            // Отправка push через Firebase/OneSignal
            // PushService::send($user->device_token, $broadcast);
        }
    }
    
    /**
     * Отправить запланированное оповещение сейчас
     */
    public function sendNow(int $id): void
    {
        $broadcast = Broadcast::find($id);
        if ($broadcast && $broadcast->status === 'scheduled') {
            $broadcast->update([
                'status' => 'sent',
                'sent_at' => now(),
                'scheduled_at' => null,
            ]);
            $this->sendRealBroadcast($broadcast);
            $this->dispatch('show-toast', type: 'success', message: 'Оповещение отправлено');
            $this->dispatch('$refresh');
        }
    }

    /**
     * Создать копию существующего оповещения
     */
    public function duplicateBroadcast(int $id): void
    {
        $original = Broadcast::find($id);
        if ($original) {
            $new = $original->replicate();
            $new->status = 'draft';
            $new->sent_at = null;
            $new->scheduled_at = null;
            $new->created_at = now();
            $new->save();
            
            $this->dispatch('show-toast', type: 'success', message: 'Оповещение продублировано');
            $this->dispatch('$refresh');
        }
    }

    /**
     * Удалить оповещение
     */
    public function deleteBroadcast(int $id): void
    {
        $broadcast = Broadcast::find($id);
        if ($broadcast) {
            $broadcast->delete();
            $this->dispatch('show-toast', type: 'success', message: 'Оповещение удалено');
            $this->dispatch('$refresh');
        }
    }

    /**
     * Получить список уведомлений с пагинацией и фильтрацией
     */
    #[Computed]
    public function broadcasts()
    {
        return Broadcast::query()
            ->with('user:id,name,email')
            ->select('id', 'user_id', 'type', 'title', 'message', 'status', 'scheduled_at', 'sent_at', 'created_at')
            ->when($this->search, function ($query) {
                $search = $this->search;
                $query->where('title', 'like', "%{$search}%")->orWhere('message', 'like', "%{$search}%");
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->typeFilter !== 'all', function ($query) {
                $query->where('type', $this->typeFilter);
            })
            ->latest()
            ->paginate($this->perPage);
    }

    /**
     * Получить статистику по статусам уведомлений
     */
    #[Computed]
    public function counts()
    {
        $counts = Broadcast::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'draft' => $counts['draft'] ?? 0,
            'sent' => $counts['sent'] ?? 0,
            'scheduled' => $counts['scheduled'] ?? 0,
            'total' => array_sum($counts),
        ];
    }

    /**
     * Получить список пользователей для комбобокса (с кешированием)
     */
    #[Computed(persist: true)]
    public function users()
    {
        return Cache::remember('admin_users_list', 3600, function () {
            $users = User::orderBy('name')->get(['id', 'name']);
            $options = [['value' => '', 'label' => 'Всем пользователям']];
            foreach ($users as $user) {
                $options[] = ['value' => $user->id, 'label' => ' (ID: ' . $user->id . ') ' . $user->name];
            }
            return $options;
        });
    }

    /**
     * Обновить поиск
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Обновить фильтр статуса
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }
}; ?>


<!--
    Управление оповещениями
    - CRUD операции: создание, редактирование, удаление
    - Фильтрация по статусу и типу
    - Поиск по названию и содержимому
    - Пагинация 8 записей на страницу
    - Дублирование и отправка запланированных уведомлений
-->

<div x-data="{ 
    showModal: false, 
    showEditModal: false,
    openEditModal() {
        this.showEditModal = true;
    }
}" 
@open-edit-modal.window="openEditModal()"
class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            Оповещения пользователей
            @if ($this->counts['draft'] > 0)
                <x-ui.badge variant="warning" size="sm">
                    {{ $this->counts['draft'] }} черновиков
                </x-ui.badge>
            @endif
        </h1>

        <x-ui.button @click="showModal = true" variant="default" size="sm">
            <x-lucide-plus class="w-4 h-4" />
            Создать оповещение
        </x-ui.button>
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="setStatusFilter('all')"
                variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все
                <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setStatusFilter('draft')"
                variant="{{ $statusFilter === 'draft' ? 'default' : 'secondary' }}" size="sm">
                Черновики
                <x-ui.badge size="xs" variant="warning">{{ $this->counts['draft'] }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setStatusFilter('scheduled')"
                variant="{{ $statusFilter === 'scheduled' ? 'default' : 'secondary' }}" size="sm">
                Запланированы
                <x-ui.badge size="xs" variant="info">{{ $this->counts['scheduled'] }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setStatusFilter('sent')"
                variant="{{ $statusFilter === 'sent' ? 'default' : 'secondary' }}" size="sm">
                Отправлены
                <x-ui.badge size="xs" variant="success">{{ $this->counts['sent'] }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <x-ui.select wire:model.live="typeFilter" class="w-40">
                <x-ui.select-trigger>
                    <x-ui.select-value placeholder="Тип" />
                </x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Все типы</x-ui.select-item>
                    <x-ui.select-item value="system">Системные</x-ui.select-item>
                    <x-ui.select-item value="email">Email</x-ui.select-item>
                    <x-ui.select-item value="push">Push</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            <div class="relative w-64">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по названию..."
                    class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="$set('search', '')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Таблица уведомлений -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head>Название</x-ui.table-head>
                <x-ui.table-head>Тип</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Кому</x-ui.table-head>
                <x-ui.table-head>Дата создания</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->broadcasts as $broadcast)
                <x-ui.table-row wire:key="broadcast-{{ $broadcast->id }}">
                    <x-ui.table-cell class="text-muted-foreground text-xs">{{ $broadcast->id }}</x-ui.table-cell>
                        <x-ui.table-cell class="max-w-[25rem] whitespace-normal">
                            <div class="min-w-0">
                                <div class="font-medium text-sm line-clamp-1" title="{{ $broadcast->title }}">
                                    {{ $broadcast->title }}
                                </div>
                                <div class="text-xs text-muted-foreground line-clamp-1" title="{{ $broadcast->message }}">
                                    {{ $broadcast->message }}
                                </div>
                            </div>
                        </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if ($broadcast->type === 'system')
                            <x-ui.badge variant="secondary" size="xs">
                                <x-lucide-monitor class="w-3 h-3 inline mr-1" />
                                Системное
                            </x-ui.badge>
                        @elseif($broadcast->type === 'email')
                            <x-ui.badge variant="warning" size="xs">
                                <x-lucide-mail class="w-3 h-3 inline mr-1" />
                                Email
                            </x-ui.badge>
                        @else
                            <x-ui.badge variant="info" size="xs">
                                <x-lucide-smartphone class="w-3 h-3 inline mr-1" />
                                Push
                            </x-ui.badge>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if ($broadcast->status === 'draft')
                            <x-ui.badge variant="warning" size="sm">Черновик</x-ui.badge>
                        @elseif($broadcast->status === 'sent')
                            <x-ui.badge variant="success" size="sm">Отправлено</x-ui.badge>
                        @else
                            {{-- Запланировано с тултипом --}}
                            <x-ui.tooltip>
                                <x-ui.tooltip-trigger>
                                    <x-ui.badge variant="info" size="sm" class="cursor-help">
                                        Запланировано
                                    </x-ui.badge>
                                </x-ui.tooltip-trigger>
                                <x-ui.tooltip-content class="bg-secondary text-secondary-foreground">
                                    <div class="text-center">
                                        <div class="font-medium">{{ $broadcast->scheduled_at?->format('d.m.Y') }}</div>
                                        <div class="text-xs text-muted-foreground">{{ $broadcast->scheduled_at?->format('H:i') }}</div>
                                    </div>
                                </x-ui.tooltip-content>
                            </x-ui.tooltip>
                        @endif
                    </x-ui.table-cell>
                   <x-ui.table-cell>
                    @if($broadcast->user_id)
                        <div class="flex items-center gap-2">
                            <x-avatar 
                                src="{{ $broadcast->user->avatar_url }}" 
                                name="{{ $broadcast->user->name }}" 
                                size="sm" 
                                userId="{{ $broadcast->user->id }}"
                                showStatus="true"
                            />
                            <div>
                                <div class="font-medium text-sm">{{ $broadcast->user->name ?? 'Пользователь' }}</div>
                                <div class="text-xs text-muted-foreground">{{ $broadcast->user->email }}</div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                                <x-lucide-users class="w-4 h-4" />
                            </div>
                            <div>
                                <div class="font-medium text-sm">Всем пользователям</div>
                                <div class="text-xs text-muted-foreground">Массовая рассылка</div>
                            </div>
                        </div>
                    @endif
                </x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs">
                        {{ $broadcast->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu>
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm">
                                    <x-lucide-more-horizontal class="w-4 h-4" />
                                </x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                @if ($broadcast->status === 'draft' || $broadcast->status === 'scheduled')
                                    <x-ui.dropdown-menu-item wire:click="editModal({{ $broadcast->id }})">
                                        <x-lucide-pencil class="w-4 h-4" />
                                        Редактировать
                                    </x-ui.dropdown-menu-item>  
                                    
                                    <x-ui.dropdown-menu-item wire:click="duplicateBroadcast({{ $broadcast->id }})">
                                        <x-lucide-copy class="w-4 h-4" />
                                        Дублировать
                                    </x-ui.dropdown-menu-item>
                                @endif
                                
                                @if($broadcast->status === 'scheduled')
                                    <x-ui.dropdown-menu-item wire:click="sendNow({{ $broadcast->id }})">
                                        <x-lucide-send class="w-4 h-4" />
                                        Отправить сейчас
                                    </x-ui.dropdown-menu-item>
                                @endif

                                <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteBroadcast({{ $broadcast->id }})">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                    Удалить
                                </x-ui.dropdown-menu-item>
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="7" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-bell-off class="w-12 h-12 opacity-30" />
                            <p>Нет уведомлений</p>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->broadcasts->firstItem() ?? 0 }} - {{ $this->broadcasts->lastItem() ?? 0 }} из
            {{ $this->broadcasts->total() }}
        </div>
        {{ $this->broadcasts->links('partials.pagination') }}
    </div>

    {{-- МОДАЛКА СОЗДАТЬ оповещение --}}
    <div x-show="showModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" @keydown.escape.window="showModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
            <!-- Заголовок -->
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Создать оповещение</h2>
                <button @click="showModal = false"
                    class="text-muted-foreground hover:text-foreground transition-colors">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <!-- Форма -->
            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-3">
                    <x-ui.label for="modalTitle">Заголовок</x-ui.label>
                    <x-ui.input id="modalTitle" wire:model="modalTitle" placeholder="Введите заголовок оповещения"
                        class="w-full" />
                </div>

                <div class="flex flex-col gap-3">
                    <x-ui.label for="modalMessage">Сообщение</x-ui.label>
                    <x-ui.textarea :rows="4" :max-rows="6" id="modalMessage" wire:model="modalMessage"
                        placeholder="Введите текст оповещения" class="w-full resize-none little-scroll" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-3">
                        <x-ui.label for="modalType">Тип оповещения</x-ui.label>
                        <x-ui.combobox wire:model="modalType" placeholder="Выберите тип..."
                            searchPlaceholder="Выберите тип..." width="w-full" empty="Нет типа." :options="[
                                ['value' => 'system', 'label' => 'Системное'],
                                ['value' => 'email', 'label' => 'Email'],
                                ['value' => 'push', 'label' => 'Push'],
                            ]" />
                    </div>

                    <div class="flex flex-col gap-3">
                        <x-ui.label for="selectedUserId">Получатель</x-ui.label>
                        <x-ui.combobox wire:model="selectedUserId" placeholder="Выберите получателя"
                            searchPlaceholder="Поиск пользователя..." empty="Пользователь не найден" width="w-full"
                            :options="$this->users" />
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <x-ui.label for="scheduledDate">Запланировать отправку</x-ui.label>
                    <x-ui.input id="scheduledDate" wire:model="scheduledDate" type="datetime-local" class="w-full" />
                </div>
            </div>

            <!-- Кнопки -->
            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="showModal = false" variant="outline" size="sm">
                    Отмена
                </x-ui.button>
                <x-ui.button @click="showModal = false" wire:click="sendBroadcast" variant="default" size="sm">
                    <x-lucide-send class="w-4 h-4" />
                    Отправить
                </x-ui.button>
            </div>
        </div>
    </div>

    {{-- МОДАЛКА РЕДАКТИРОВАНИЯ --}}
    <div x-show="showEditModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" @keydown.escape.window="showEditModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
            <!-- Заголовок -->
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Редактировать оповещение</h2>
                <button @click="showEditModal = false"
                    class="text-muted-foreground hover:text-foreground transition-colors">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <!-- Форма -->
            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-3">
                    <x-ui.label for="editTitle">Заголовок</x-ui.label>
                    <x-ui.input id="editTitle" wire:model="modalTitle" placeholder="Введите заголовок оповещения"
                        class="w-full" />
                </div>

                <div class="flex flex-col gap-3">
                    <x-ui.label for="editMessage">Сообщение</x-ui.label>
                    <x-ui.textarea :rows="4" :max-rows="6" id="editMessage" wire:model="modalMessage"
                        placeholder="Введите текст оповещения" class="w-full resize-none little-scroll" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-3">
                        <x-ui.label for="editType">Тип оповещения</x-ui.label>
                        <x-ui.combobox wire:model="modalType" placeholder="Выберите tipo..."
                            searchPlaceholder="Выберите тип..." width="w-full" empty="Нет типа." :options="[
                                ['value' => 'system', 'label' => 'Системное'],
                                ['value' => 'email', 'label' => 'Email'],
                                ['value' => 'push', 'label' => 'Push'],
                            ]" />
                    </div>

                    <div class="flex flex-col gap-3">
                        <x-ui.label for="editUser">Получатель</x-ui.label>
                        <x-ui.combobox wire:model="selectedUserId" placeholder="Выберите получателя"
                            searchPlaceholder="Поиск пользователя..." empty="Пользователь не найден" width="w-full"
                            :options="$this->users" />
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <x-ui.label for="editDate">Запланировать отправку</x-ui.label>
                    <x-ui.input id="editDate" wire:model="scheduledDate" type="datetime-local" class="w-full" />
                </div>
            </div>

            <!-- Кнопки -->
            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="showEditModal = false" variant="outline" size="sm">
                    Отмена
                </x-ui.button>
                <x-ui.button @click="showEditModal = false" wire:click="updateBroadcast" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4" />
                    Сохранить
                </x-ui.button>
            </div>
        </div>
    </div>
</div>