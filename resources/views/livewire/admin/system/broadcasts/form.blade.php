<?php

use App\Models\AdminLog;
use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    /** @var Broadcast|null Текущая редактируемая рассылка (null при создании) */
    public ?Broadcast $broadcast = null;
    
    /** @var int|null ID редактируемой рассылки (для wire:key) */
    public ?int $editingId = null;

    // === ОСНОВНЫЕ ПОЛЯ ===
    public string $title = '';
    public string $type = 'in_app';
    public string $message = ''; // Plain text для Push/In-App
    public string $emailBody = ''; // HTML для Email
    public string $actionUrl = '';
    public string $scheduledDate = '';

    // === ФИЛЬТРЫ АУДИТОРИИ ===
    public array $filters = [
        'gender' => null,
        'is_premium' => null,
        'city' => null,
        'age_from' => null,
        'age_to' => null,
        'last_seen_days' => null,
        'device_os' => null,
        'has_photo' => null,
    ];

    // === ВЫБОР КОНКРЕТНОГО ЮЗЕРА ===
    public ?int $selectedUserId = null;
    public ?string $selectedUserName = null;
    
    public string $backUrl = '';
    public bool $showConfirmModal = false;
    
    /** @var int Ключ для принудительного ререндера Alpine-компонентов (Selects) при сбросе */
    public int $formKey = 0;

    /**
     * Инициализация компонента.
     * Заполняет свойства из БД, если мы редактируем существующую рассылку.
     *
     * @param Broadcast|null $broadcast
     * @return void
     */
    public function mount(?Broadcast $broadcast = null): void
    {
        $previousUrl = url()->previous();
        $currentUrl = url()->current();
        $this->backUrl = ($previousUrl && $previousUrl !== $currentUrl) 
            ? $previousUrl 
            : route('admin.system.broadcasts.index');

        if ($broadcast && $broadcast->exists) { 
            $this->broadcast = $broadcast;
            $this->editingId = $this->broadcast->id;
            $this->title = $this->broadcast->title;
            $this->type = $this->broadcast->type;
            $this->message = $this->broadcast->message;
            $this->emailBody = $this->broadcast->email_body ?? '';
            
            // Безопасное извлечение URL из JSON поля data
            $data = $this->broadcast->data ?? [];
            $this->actionUrl = $data['action_url'] ?? '';
            
            $this->scheduledDate = $this->broadcast->scheduled_at?->format('Y-m-d\TH:i') ?? '';

            $target = $this->broadcast->target_audience ?? [];

            if (isset($target['user_id'])) {
                $this->selectedUserId = $target['user_id'];
                // Ищем даже удаленных (withTrashed), чтобы получить имя
                $this->selectedUserName = $target['user_name'] ?? User::withTrashed()->find($this->selectedUserId)?->name ?? 'Удаленный юзер';
            } else {
                $this->filters = array_merge($this->filters, $target);
            }
        }
    }

        /**
     * Хук Livewire: срабатывает при ЛЮБОМ изменении в массиве $filters.
     * Превращает пустые строки "" в null.
     * Это спасает валидацию 'in:male,female' от падения на пустой строке.
     */
    public function updatedFilters($value, $key): void
    {
        if ($value === '' || $value === 'all') {
            $this->filters[$key] = null;
        }
    }

    /**
     * Правила валидации данных.
     * Использует conditional rules (required_if, required_unless) и prohibited_unless.
     *
     * @return array
     */
    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'message' => 'required_unless:type,email|string|max:5000',
            'emailBody' => 'required_if:type,email|string|max:10000',
            'type' => 'required|in:in_app,push,email',
            'actionUrl' => 'nullable|url|max:500',
            'scheduledDate' => 'nullable|date|after_or_equal:now|before_or_equal:' . now()->addYear()->toDateTimeString(),
            'selectedUserId' => 'nullable|integer|exists:users,id', 
            'filters.gender' => ['nullable', 'in:male,female', 'prohibited_unless:selectedUserId,null'],
            // РАЗРЕШАЕМ true И false
            'filters.is_premium' => ['nullable', 'in:true,false', 'prohibited_unless:selectedUserId,null'],
            'filters.city' => ['nullable', 'string', 'max:100', 'prohibited_unless:selectedUserId,null'],
            'filters.age_from' => [
                'nullable', 'integer', 'min:18', 'max:99', 'prohibited_unless:selectedUserId,null',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!empty($this->filters['age_to']) && $value > $this->filters['age_to']) {
                        $fail('Возраст "от" не может быть больше возраста "до".');
                    }
                },
            ],
            'filters.age_to' => ['nullable', 'integer', 'min:18', 'max:99', 'prohibited_unless:selectedUserId,null'],
            'filters.device_os' => ['nullable', 'in:ios,android,web', 'prohibited_unless:selectedUserId,null'],
            'filters.last_seen_days' => ['nullable', 'integer', 'in:3,7,30', 'prohibited_unless:selectedUserId,null'],
            // РАЗРЕШАЕМ true И false
            'filters.has_photo' => ['nullable', 'in:true,false', 'prohibited_unless:selectedUserId,null'],
        ];
    }

    /**
     * Обработчик события выбора юзера из дочернего компонента поиска.
     * Сбрасывает фильтры, так как выбран конкретный юзер.
     *
     * @param int $id
     * @param string $name
     * @return void
     */
    #[On('user-selected')]
    public function setUser(int $id, string $name): void
    {
        $this->selectedUserId = $id;
        $this->selectedUserName = $name; 
        $this->reset('filters');
        $this->resetValidation();
        $this->formKey++; // Обновляем UI блоков фильтров
    }

    /**
     * Очистка выбранного юзера.
     *
     * @return void
     */
    public function clearSelectedUser(): void
    {
        $this->reset(['selectedUserId', 'selectedUserName']);
        $this->formKey++;
    }

    /**
     * Полный сброс настроек и фильтров (кнопка "Сбросить").
     * Не трогает заголовок и основной текст.
     *
     * @return void
     */
    public function resetSettingsAndFilters(): void
    {
        $this->reset('type', 'scheduledDate', 'selectedUserId', 'selectedUserName', 'filters', 'emailBody');
        $this->resetValidation();
        $this->formKey++;
        $this->dispatch('show-toast', type: 'info', message: 'Настройки и аудитория сброшены');
    }

    /**
     * Формирование человекочитаемой строки аудитории для модалки подтверждения.
     *
     * @return string
     */
     #[Computed]
    public function audienceSummary(): string
    {
        if ($this->selectedUserId) {
            return "Только юзер: {$this->selectedUserName} (ID: {$this->selectedUserId})";
        }

        $parts = [];
        if (!empty($this->filters['gender'])) $parts[] = $this->filters['gender'] === 'male' ? 'Мужчины' : 'Женщины';
        
        // Обработка VIP
        if (!empty($this->filters['is_premium'])) {
            $parts[] = $this->filters['is_premium'] === 'true' ? 'Только VIP' : 'Без VIP';
        }
        
        if (!empty($this->filters['city'])) $parts[] = 'Город: ' . $this->filters['city'];
        
        $ageParts = [];
        if (!empty($this->filters['age_from'])) $ageParts[] = 'от ' . $this->filters['age_from'];
        if (!empty($this->filters['age_to'])) $ageParts[] = 'до ' . $this->filters['age_to'];
        if (!empty($ageParts)) $parts[] = 'Возраст: ' . implode(' ', $ageParts);

        if (!empty($this->filters['device_os'])) {
            $osMap = ['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web'];
            $parts[] = $osMap[$this->filters['device_os']] ?? $this->filters['device_os'];
        }
        if (!empty($this->filters['last_seen_days'])) $parts[] = 'Не заходили >' . $this->filters['last_seen_days'] . 'д';
        
        // Обработка Фото
        if (!empty($this->filters['has_photo'])) {
            $parts[] = $this->filters['has_photo'] === 'true' ? 'С фото' : 'Без фото';
        }

        return empty($parts) ? 'Все пользователи (без фильтров)' : implode(', ', $parts);
    }

    /**
     * Открытие модалки подтверждения.
     * Предварительно валидирует данные. При ошибке показывает тост и кидает исключение.
     *
     * @return void
     */
    public function openConfirmModal(): void
    {
        try {
            $this->validate($this->rules());
            $this->showConfirmModal = true;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка валидации! Проверьте выделенные поля.');
            throw $e; 
        }
    }

    /**
     * Основное сохранение (создание или обновление).
     * Очищает HTML от XSS, формирует данные, пишет логи и редиректит.
     *
     * @return void
     */
    public function confirmSave(): void
    {
        if ($this->broadcast && $this->broadcast->exists && !in_array($this->broadcast->status, ['draft', 'scheduled'])) {
            $this->dispatch('show-toast', type: 'error', message: 'Эта рассылка заблокирована для редактирования.');
            return;
        }

        try {
            $this->validate($this->rules());

            $status = !empty($this->scheduledDate) ? 'scheduled' : 'draft';

            $targetAudience = [];
            if ($this->selectedUserId) {
                $targetAudience = [
                    'user_id' => $this->selectedUserId,
                    'user_name' => $this->selectedUserName,
                ];
            } else {
                // Вычищаем пустые строки
                $targetAudience = array_filter($this->filters, fn($value) => !is_null($value) && $value !== '');
                
                // ПРЕОБРАЗУЕМ СТРОКИ В НАСТОЯЩИЕ БУЛЕВЫ ЗНАЧЕНИЯ ДЛЯ БД
                if (isset($targetAudience['is_premium'])) {
                    $targetAudience['is_premium'] = $targetAudience['is_premium'] === 'true';
                }
                if (isset($targetAudience['has_photo'])) {
                    $targetAudience['has_photo'] = $targetAudience['has_photo'] === 'true';
                }
            }

            $cleanEmailBody = $this->emailBody;
            if ($this->type === 'email' && class_exists(\Mews\Purifier\Facades\Purifier::class)) {
                $cleanEmailBody = clean($this->emailBody);
            }

            $data = [
                'admin_id' => auth()->id(),
                'type' => $this->type,
                'title' => $this->title,
                'message' => $this->type === 'email' ? strip_tags($cleanEmailBody) : $this->message,
                'email_body' => $this->type === 'email' ? $cleanEmailBody : null,
                'data' => !empty($this->actionUrl) ? ['action_url' => $this->actionUrl] : null,
                'target_audience' => $targetAudience,
                'status' => $status,
                'scheduled_at' => $this->scheduledDate ?: null,
            ];

            $logFields = ['title', 'message', 'email_body', 'type', 'data', 'target_audience', 'status', 'scheduled_at'];

            if ($this->broadcast && $this->broadcast->exists) {
                $before = $this->broadcast->only($logFields);
                $this->broadcast->update($data);
                $after = $this->broadcast->fresh()->only($logFields);
                
                AdminLog::record('broadcast.update', $this->broadcast, auth()->user(), $before, $after);
                Log::info("Админ обновил рассылку", ['broadcast_id' => $this->broadcast->id, 'admin_id' => auth()->id()]);
                
                $this->dispatch('show-toast', type: 'success', message: 'Рассылка обновлена!');
            } else {
                $broadcast = Broadcast::create($data);
                AdminLog::record('broadcast.create', $broadcast, auth()->user());
                Log::info("Админ создал рассылку", ['broadcast_id' => $broadcast->id, 'admin_id' => auth()->id()]);
                
                $message = $status === 'scheduled' ? 'Рассылка запланирована!' : 'Рассылка сохранена как черновик!';
                $this->dispatch('show-toast', type: 'success', message: $message);
            }

            $this->showConfirmModal = false;
            $this->redirect(route('admin.system.broadcasts.index'), navigate: true);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка валидации! Проверьте выделенные поля.');
            throw $e;
        } catch (\Exception $e) {
            Log::error("Ошибка сохранения рассылки: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при сохранении!');
            $this->showConfirmModal = false;
        }
    }
}; 
?>

@php
    // ФРОНТЕНД ФЛАГ: Определяем, заблокирована ли форма для редактирования (Read-Only Mode)
    $isLocked = $broadcast && $broadcast->exists && !in_array($broadcast->status, ['draft', 'scheduled']);
@endphp

<!-- ПЕРЕДАЕМ ФЛАГ В ALPINE ЧТОБЫ TINYMCE СТАЛ READONLY -->
<div class="space-y-6 pb-6" x-data="broadcastForm({{ $isLocked ? 'true' : 'false' }})">
    <!-- Заголовок и хлебные крошки -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-3">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors mt-1">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <a href="{{ route('admin.system.broadcasts.index') }}" wire:navigate class="hover:text-foreground transition-colors">Рассылки</a>
                    <x-lucide-chevron-right class="w-4 h-4" />
                    <span>{{ $broadcast && $broadcast->exists ? 'Просмотр / Редактирование' : 'Создание' }}</span>
                </div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-radio class="w-6 h-6" />
                    {{ $title ?: 'Новая рассылка' }}
                </h1>
            </div>
        </div>

        @if($isLocked)
            <x-ui.badge variant="destructive" class="text-sm py-1 px-3">
                <x-lucide-lock class="w-4 h-4 mr-1" /> Рассылка заблокирована ({{ $broadcast->status }})
            </x-ui.badge>
        @else
        <div class="flex items-center gap-2">
           <x-ui.button wire:click="resetSettingsAndFilters" variant="outline" size="sm" wire:confirm="Сбросить настройки и фильтры аудитории?">
                <x-lucide-rotate-ccw class="w-4 h-4 inline" /> 
                <span>Сбросить</span>
            </x-ui.button>

            <x-ui.button wire:click="openConfirmModal" variant="default" size="sm" wire:loading.attr="disabled" wire:target="openConfirmModal">
                <span wire:loading.remove wire:target="openConfirmModal" class="flex items-center gap-2">
                    <x-lucide-list-checks class="w-4 h-4 inline" /> 
                    <span>Проверить и сохранить</span>
                </span>
                <span wire:loading wire:target="openConfirmModal" class="flex items-center gap-2">
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> 
                    <span>Проверка...</span>
                </span>
            </x-ui.button>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
        
        <!-- ЛЕВАЯ КОЛОНКА (Контент) -->       
        <div class="flex lg:col-span-2">
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm flex flex-col gap-4 w-full min-h-[500px]">
                <div class="flex flex-col gap-2">
                    <x-ui.label for="title">Заголовок уведомления <span class="text-destructive">*</span></x-ui.label>
                    <x-ui.input id="title" wire:model="title" placeholder="Например: Скидки на VIP!" :readonly="$isLocked" />
                    @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2 flex-1 min-h-0">
                    @if($type === 'email')
                        <!-- wire:key ИЗОЛИРУЕТ DOM ДЛЯ ПЕРЕКЛЮЧЕНИЯ РЕДАКТОРОВ БЕЗ КОНФЛИКТОВ ALPINE -->
                        <div wire:key="editor-email-mode" class="flex flex-col gap-2 flex-1 min-h-0">
                            <div class="flex items-center justify-between">
                                <x-ui.label for="emailBody">HTML верстка письма <span class="text-destructive">*</span></x-ui.label>
                                <span class="text-xs text-blue-500 bg-blue-500/10 px-2 py-0.5 rounded-md">Email режим</span>
                            </div>
                            
                            <div class="flex flex-col gap-2 flex-1 min-h-0 relative">
                                <div x-show="!isEditorLoaded" x-cloak class="absolute inset-0 top-8 flex items-center justify-center bg-card border border-border rounded-md z-10">
                                    <div class="flex flex-col items-center gap-3 text-muted-foreground">
                                        <x-lucide-loader-2 class="w-8 h-8 animate-spin text-primary" />
                                        <span class="text-sm font-medium">Загрузка редактора...</span>
                                    </div>
                                </div>

                                <div wire:ignore class="tinymce-wrapper flex-1 min-h-0 flex flex-col" x-show="isEditorLoaded" x-cloak>
                                    <textarea id="tinyMceEmailBody" wire:model="emailBody" placeholder="Введите текст письма..."></textarea>
                                </div>
                                
                                @error('emailBody') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @else
                        <div wire:key="editor-plain-mode" class="flex flex-col gap-2 flex-1 min-h-0">
                            <div class="flex items-center justify-between">
                                <x-ui.label for="message">Текст сообщения <span class="text-destructive">*</span></x-ui.label>
                                <span class="text-xs text-green-500 bg-green-500/10 px-2 py-0.5 rounded-md">{{ $type === 'push' ? 'Push режим' : 'In-App режим' }}</span>
                            </div>
                            
                            <x-ui.textarea 
                                id="message" 
                                wire:model="message" 
                                rows="10" 
                                placeholder="Введите обычный текст без HTML тегов..." 
                                class="resize-none flex-1 min-h-[300px]"
                                :readonly="$isLocked"
                            ></x-ui.textarea>
                            <p class="text-xs text-muted-foreground">Обычный текст. HTML-теги здесь не поддерживаются и будут видны юзеру как текст.</p>
                            @error('message') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="actionUrl">URL для перехода (необязательно)</x-ui.label>
                    <x-ui.input id="actionUrl" wire:model="actionUrl" type="url" placeholder="https://site.com/promo/vip" :readonly="$isLocked" />
                    <p class="text-xs text-muted-foreground">Куда вести пользователя при клике на пуш, кнопку в письме или колокольчик.</p>
                    @error('actionUrl') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- ПРАВАЯ КОЛОНКА (Настройки и Фильтры) -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- wire:key С formKey ПРИНУДИТЕЛЬНО ПЕРЕРИСОВЫВАЕТ СЕЛЕКТЫ ПРИ СБРОСЕ -->
            <div wire:key="settings-block-{{ $formKey }}" class="bg-card border border-border rounded-lg p-6 shadow-sm space-y-4">
                <h3 class="text-sm font-medium flex items-center gap-2 text-muted-foreground uppercase tracking-wide">
                    <x-lucide-settings class="w-4 h-4" /> Параметры отправки
                </h3>
                <div class="flex flex-col gap-2">
                    <x-ui.label class="text-xs">Тип уведомления <span class="text-destructive">*</span></x-ui.label>
                    <x-ui.select wire:model.live="type">
                        <x-ui.select-trigger :disabled="$isLocked"><x-ui.select-value /></x-ui.select-trigger>
                        <x-ui.select-content>
                            <x-ui.select-item value="in_app">В приложении (Колокольчик)</x-ui.select-item>
                            <x-ui.select-item value="email">Email (Письмо)</x-ui.select-item>
                            <x-ui.select-item value="push">Push (Мобильное/Web)</x-ui.select-item>
                        </x-ui.select-content>
                    </x-ui.select>
                    @if($type === 'email')
                        <p class="text-xs text-blue-500 mt-1 flex gap-1"><x-lucide-info class="w-3 h-3" /> Отправится только тем, у кого включена настройка "Email".</p>
                    @elseif($type === 'push')
                        <p class="text-xs text-green-500 mt-1 flex gap-1"><x-lucide-info class="w-3 h-3" /> Отправится только тем, у кого включены Push-уведомления.</p>
                    @endif
                </div>
                
                <div class="flex flex-col gap-2">
                    <x-ui.label for="scheduledDate" class="text-xs">Запланировать отправку</x-ui.label>
                    
                    @if($isLocked)
                        <!-- ЕСЛИ ЗАБЛОКИРОВАНО: Выводим нативный disabled инпут, календарь не кликабельный -->
                        <input 
                            type="datetime-local" 
                            value="{{ $scheduledDate }}" 
                            disabled 
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        >
                    @else
                        <!-- ЕСЛИ РЕДАКТИРУЕМ: Красивый пикер (используем wire:model без .live, чтобы не было глюков с выбором времени) -->
                        <x-ui.datetime-picker 
                            id="scheduledDate" 
                            wire:model="scheduledDate" 
                            type="datetime-local" 
                            max="{{ now()->addYear()->format('Y-m-d\TH:i') }}" 
                        />                
                    @endif

                    @error('scheduledDate') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    @if(empty($scheduledDate))
                        <p class="text-xs text-muted-foreground">Если пусто — сохранится как черновик.</p>
                    @endif
                </div>
            </div>
            
            <div wire:key="filters-block-{{ $formKey }}" class="bg-card border border-border rounded-lg p-6 shadow-sm space-y-4">
                <h3 class="text-sm font-medium flex items-center gap-2 text-muted-foreground uppercase tracking-wide">
                    <x-lucide-users class="w-4 h-4" /> Целевая аудитория
                </h3>
                
                <div class="flex flex-col gap-2">
                    <x-ui.label class="text-xs">Конкретный юзер (отменяет все фильтры ниже)</x-ui.label>
                    @if(!$selectedUserId && !$isLocked)
                        <livewire:admin.system.broadcasts.search wire:key="user-search-{{ $editingId ?? 'new' }}-{{ $formKey }}" />
                    @else
                        <div wire:key="user-selected-{{ $selectedUserId ?? 'none' }}" class="flex items-center justify-between p-2 border border-border rounded-md bg-muted/20">
                            @if($selectedUserId)
                                <span class="text-sm font-medium truncate">{{ $selectedUserName }} (ID:{{ $selectedUserId }})</span>
                            @else
                                <span class="text-sm text-muted-foreground">Не выбран</span>
                            @endif
                            
                            @if($selectedUserId && !$isLocked)
                            <button type="button" wire:click="clearSelectedUser" class="text-muted-foreground hover:text-destructive">
                                <x-lucide-x class="w-4 h-4" />
                            </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="{{ $selectedUserId ? 'opacity-40 pointer-events-none' : '' }} border-t border-border pt-4 grid grid-cols-2 gap-3 relative">
                    @if($selectedUserId)
                        <div class="absolute inset-0 z-10 flex items-center justify-center">
                            <x-lucide-lock class="w-24 h-24 text-muted-foreground" />
                        </div>
                    @endif

                    <!-- Пол -->
                    <div class="flex flex-col gap-1">
                        <x-ui.label class="text-xs">Пол</x-ui.label>
                        <x-ui.select wire:model.live="filters.gender">
                            <x-ui.select-trigger :disabled="$isLocked"><x-ui.select-value placeholder="Все" /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="">Все</x-ui.select-item>
                                <x-ui.select-item value="male">Мужчины</x-ui.select-item>
                                <x-ui.select-item value="female">Женщины</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                        @error('filters.gender') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- VIP статус -->
                    <div class="flex flex-col gap-1">
                        <x-ui.label class="text-xs">VIP статус</x-ui.label>
                        <x-ui.select wire:model.live="filters.is_premium">
                            <x-ui.select-trigger :disabled="$isLocked"><x-ui.select-value placeholder="Все" /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="">Все</x-ui.select-item>
                                <x-ui.select-item value="true">Только VIP</x-ui.select-item>
                                <x-ui.select-item value="false">Без VIP</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                        @error('filters.is_premium') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Город -->
                    <div class="flex flex-col gap-1 col-span-2">
                        <x-ui.label class="text-xs">Город</x-ui.label>
                        <x-ui.input wire:model="filters.city" placeholder="Например: Москва" :readonly="$isLocked" />
                        @error('filters.city') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Возраст -->           
                    <div class="flex flex-col gap-1 col-span-2">
                        <x-ui.label class="text-xs">Возраст</x-ui.label>
                        <div class="flex items-center gap-2">
                            <x-ui.input type="number" wire:model="filters.age_from" placeholder="От" min="18" :readonly="$isLocked" />
                            <span class="text-muted-foreground">—</span>
                            <x-ui.input type="number" wire:model="filters.age_to" placeholder="До" min="18" :readonly="$isLocked" />
                        </div>
                        @error('filters.age_from') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        @error('filters.age_to') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- ОС устройства -->
                    <div class="flex flex-col gap-1">
                        <x-ui.label class="text-xs">ОС устройства</x-ui.label>
                        <x-ui.select wire:model.live="filters.device_os">
                            <x-ui.select-trigger :disabled="$isLocked"><x-ui.select-value placeholder="Все" /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="">Все</x-ui.select-item>
                                <x-ui.select-item value="ios">iOS</x-ui.select-item>
                                <x-ui.select-item value="android">Android</x-ui.select-item>
                                <x-ui.select-item value="web">Web</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                        @error('filters.device_os') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Активность -->
                    <div class="flex flex-col gap-1">
                        <x-ui.label class="text-xs">Не заходили</x-ui.label>
                        <x-ui.select wire:model.live="filters.last_seen_days">
                            <x-ui.select-trigger :disabled="$isLocked"><x-ui.select-value placeholder="Любая" /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="">Любая</x-ui.select-item>
                                <x-ui.select-item value="3">> 3 дней</x-ui.select-item>
                                <x-ui.select-item value="7">> 7 дней</x-ui.select-item>
                                <x-ui.select-item value="30">> 30 дней</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                        @error('filters.last_seen_days') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Фото -->
                    <div class="flex flex-col gap-1 col-span-2">
                        <x-ui.label class="text-xs">Наличие фото</x-ui.label>
                        <x-ui.select wire:model.live="filters.has_photo">
                            <x-ui.select-trigger :disabled="$isLocked"><x-ui.select-value placeholder="Любое" /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="">Любое</x-ui.select-item>
                                <x-ui.select-item value="true">С фото</x-ui.select-item>
                                <x-ui.select-item value="false">Без фото</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                        @error('filters.has_photo') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!$isLocked)
    <!-- МОДАЛКА ПОДТВЕРЖДЕНИЯ (ТОЛЬКО ДЛЯ РЕДАКТИРУЕМЫХ) -->
    <div x-show="$wire.showConfirmModal" x-cloak @click.self="$wire.showConfirmModal = false" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" 
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0 scale-95" 
         x-transition:enter-end="opacity-100 scale-100" 
         @keydown.escape.window="$wire.showConfirmModal = false">
         
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-4 overflow-hidden">
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-500/10 rounded-full">
                        <x-lucide-list-checks class="w-6 h-6 text-blue-500" />
                    </div>
                    <h2 class="text-lg font-semibold">Проверка перед сохранением</h2>
                </div>
                
                <p class="text-sm text-muted-foreground">Убедитесь, что параметры рассылки настроены верно.</p>

                <div class="bg-muted/30 border border-border rounded-md p-4 space-y-3">
                    <div>
                        <p class="text-xs text-muted-foreground uppercase mb-1">Аудитория:</p>
                        <p class="text-sm font-medium">{{ $this->audienceSummary }}</p>
                    </div>
                    <div class="border-t border-border pt-3">
                        <p class="text-xs text-muted-foreground uppercase mb-1">Тип уведомления:</p>
                        <p class="text-sm font-medium">
                            {{ $type === 'in_app' ? 'В приложении (Колокольчик)' : ($type === 'email' ? 'Email (Письмо)' : 'Push (Уведомление)') }}
                        </p>
                    </div>
                    <div class="border-t border-border pt-3">
                        <p class="text-xs text-muted-foreground uppercase mb-1">Заголовок:</p>
                        <p class="text-sm font-medium">{{ $title }}</p>
                    </div>
                    @if(!empty($scheduledDate))
                        <div class="border-t border-border pt-3">
                            <p class="text-xs text-muted-foreground uppercase mb-1">Отправка запланирована:</p>
                            <p class="text-sm font-medium">{{ \Carbon\Carbon::parse($scheduledDate)->format('d.m.Y H:i') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showConfirmModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                
                <x-ui.button wire:click="confirmSave" variant="default" size="sm" wire:loading.attr="disabled" wire:target="confirmSave">
                    <span wire:loading.remove wire:target="confirmSave" class="flex items-center gap-2">
                        <x-lucide-save class="w-4 h-4 inline" /> 
                        <span>
                            @if($broadcast && $broadcast->exists)
                                Сохранить изменения
                            @elseif(!empty($scheduledDate))
                                Запланировать
                            @else
                                Сохранить черновик
                            @endif
                        </span>
                    </span>
                    <span wire:loading wire:target="confirmSave" class="flex items-center gap-2">
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> 
                        <span>Сохранение...</span>
                    </span>
                </x-ui.button>
            </div>
        </div>
    </div>
    @endif

    <style>
    .tinymce-wrapper .tox.tox-tinymce {
        border: 1px solid var(--border) !important;
        border-radius: 0.5rem !important;
        height: 100% !important; 
        display: flex !important;
        flex-direction: column !important;
    }
    .tinymce-wrapper .tox .tox-editor-container {
        flex: 1 !important;
        display: flex !important;
        flex-direction: column !important;
    }
    .tinymce-wrapper .tox .tox-edit-area {
        flex: 1 !important;
        border-top: none !important;
    }
    .tinymce-wrapper .tox .tox-toolbar-overlord,
    .tinymce-wrapper .tox .tox-toolbar__primary {
        background: var(--card) !important;
        border-bottom: 0.625rem solid var(--border) !important;
    }
    .tinymce-wrapper .tox .tox-tbtn {
        color: var(--muted-foreground) !important;
    }
    .tinymce-wrapper .tox .tox-tbtn svg {
        fill: currentColor !important;
    }
    .tinymce-wrapper .tox .tox-tbtn:hover {
        background: var(--accent) !important;
        color: var(--accent-foreground) !important;
    }
    .tinymce-wrapper .tox .tox-tbtn--enabled,
    .tinymce-wrapper .tox .tox-tbtn--enabled:hover {
        background: var(--primary) !important;
        color: var(--primary-foreground) !important;
    }
    .tinymce-wrapper .tox .tox-tbtn--select span {
        color: var(--foreground) !important;
    }       
</style>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js"></script>
<script>
    // ПРИНИМАЕМ ФЛАГ isLocked ИЗ ALPINE X-DATA
    window.broadcastForm = function (isLocked = false) {
        return {
            themeObserver: null,
            currentTheme: 'light',
            isEditorLoaded: false,
            textareaElement: null,
            typingTimer: null, 
            isLocked: isLocked, // СОХРАНЯЕМ ФЛАГ

            init() {
                this.$nextTick(() => {
                    this.setupThemeWatcher();
                    
                    this.$watch(() => this.$wire.type, (value) => {
                        if (value === 'email') {
                            this.$nextTick(() => this.waitForTinyMCE());
                        } else {
                            this.destroyTinyMCE();
                        }
                    });

                    this.$watch(() => this.$wire.formKey, () => {
                        if (this.$wire.type === 'email') {
                            this.destroyTinyMCE();
                            this.$nextTick(() => this.waitForTinyMCE());
                        }
                    });

                    if (this.$wire.type === 'email') {
                        this.waitForTinyMCE();
                    }
                });
            },

            waitForTinyMCE() {
                this.textareaElement = document.getElementById('tinyMceEmailBody');
                if (typeof tinymce !== 'undefined' && this.textareaElement) {
                    this.initTinyMCE();
                } else {
                    setTimeout(() => this.waitForTinyMCE(), 100);
                }
            },

            getTheme() {
                return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            },

            getCssVar(name) {
                return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            },

            initTinyMCE() {
                if (typeof tinymce === 'undefined' || !this.textareaElement) return;
                if (tinymce.get('tinyMceEmailBody')) return;

                this.currentTheme = this.getTheme();
                const isDark = this.currentTheme === 'dark';

                const bgColor = this.getCssVar('--background');
                const textColor = this.getCssVar('--foreground');
                const borderColor = this.getCssVar('--border');
                const mutedColor = this.getCssVar('--muted-foreground');
                const mutedBgColor = this.getCssVar('--muted');

                tinymce.init({
                    selector: '#tinyMceEmailBody',
                    license_key: 'gpl',
                    menubar: false,
                    height: '100%',
                    plugins: 'lists link table image autolink wordcount code fullscreen quickbars',
                    toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist outdent indent | link table image | code fullscreen',
                    skin: isDark ? 'oxide-dark' : 'oxide',
                    content_css: isDark ? 'dark' : 'default',
                    statusbar: false, 
                    placeholder: '',
                    readonly: this.isLocked, // TINYMCE ПЕРЕВОДИТСЯ В РЕЖИМ ЧТЕНИЯ
                    
                    content_style: `
                        body { 
                            background-color: ${bgColor} !important; 
                            color: ${textColor} !important;
                            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
                            font-size: 0.85rem; 
                            line-height: 1.6; 
                            padding: 1rem; 
                            margin: 0 !important;
                        }
                        h1, h2, h3, h4 { color: ${textColor} !important; }
                        h1 { font-size: 1.5rem; font-weight: 700; margin-top: 1rem; margin-bottom: 0.5rem; }
                        h2 { font-size: 1.25rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.5rem; }
                        p { margin: 0 0 1rem 0; }
                        a { color: #3b82f6; text-decoration: underline; }
                        blockquote { border-left: 0.25rem solid ${borderColor}; padding-left: 1rem; color: ${mutedColor}; font-style: italic; margin: 1rem 0; }
                        pre { background-color: ${mutedBgColor}; color: ${textColor}; padding: 1rem; border-radius: 0.5rem; font-family: monospace; overflow-x: auto; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 0.625rem solid ${borderColor}; padding: 0.5rem; }
                    `,
                    
                    setup: (editor) => {
                        editor.on('init', () => {
                            this.isEditorLoaded = true; 
                        });

                        editor.on('input change keyup undo redo SetContent', () => {
                            if (this.isLocked) return; // НЕ ОТПРАВЛЯЕМ ИЗМЕНЕНИЯ В LIVEWIRE ЕСЛИ ЗАБЛОКИРОВАНО
                            
                            clearTimeout(this.typingTimer);
                            this.typingTimer = setTimeout(() => {
                                if (this.textareaElement) {
                                    this.textareaElement.value = editor.getContent();
                                    this.textareaElement.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            }, 500);
                        });
                    }
                });
            },

            destroyTinyMCE() {
                if (typeof tinymce !== 'undefined' && tinymce.get('tinyMceEmailBody')) {
                    this.isEditorLoaded = false; 
                    clearTimeout(this.typingTimer);
                    tinymce.get('tinyMceEmailBody').remove();
                }
            },

            setupThemeWatcher() {
                this.themeObserver = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.attributeName === 'class') {
                            const newTheme = this.getTheme();
                            if (newTheme !== this.currentTheme) {
                                this.destroyTinyMCE();
                                setTimeout(() => this.initTinyMCE(), 50);
                            }
                        }
                    });
                });
                this.themeObserver.observe(document.documentElement, { attributes: true });
            },

            destroy() {
                if (this.themeObserver) {
                    this.themeObserver.disconnect();
                    this.themeObserver = null;
                }
                this.destroyTinyMCE();
            }
        };
    };
</script>
</div>