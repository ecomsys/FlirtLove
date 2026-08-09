<?php
use App\Models\Gift;
use App\Models\UserGift;
use App\Models\AdminLog;
use App\Enums\GiftCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $activeTab = 'catalog';
    
    public string $catalogSearch = '';
    public string $categoryFilter = 'all';

    public string $historySearch = '';
    public string $privacyFilter = 'all';

    public bool $showGiftModal = false;
    public ?int $editingGiftId = null;
    public string $modalName = '';
    public string $modalSlug = '';
    public string $modalImageUrl = '';
    public int $modalPrice = 100;
    public string $modalCategory = 'romantic';
    public bool $modalIsActive = true;

    public function mount(): void
    {
        $this->activeTab = session('admin_gifts_tab', 'catalog');
    }

    #[On('media-selected')]
    public function setMediaFromManager(int $mediaId, string $diskPath, string $collection): void
    {
        if ($collection === 'gifts') {
            $this->modalImageUrl = $diskPath; // Сохраняем чистый путь (media/gifts/...)
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        session(['admin_gifts_tab' => $tab]);
        $this->resetPage();
    }

    // ============================================
    // КАТАЛОГ ПОДАРКОВ
    // ============================================

    public function updatedCatalogSearch(): void { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    
    public function updatedModalName(string $value): void
    {
        if (is_null($this->editingGiftId)) {
            $this->modalSlug = Str::slug($value);
        }
    }

    public function createGiftModal(): void
    {
        $this->reset(['modalName', 'modalSlug', 'modalImageUrl', 'modalPrice', 'modalCategory', 'modalIsActive', 'editingGiftId']);
        $this->modalIsActive = true;
        $this->modalPrice = 100;
        $this->resetValidation();
        $this->showGiftModal = true;
    }

    public function editGiftModal(int $id): void
    {
        $gift = Gift::find($id);
        if (!$gift) return;

        $this->editingGiftId = $id;
        $this->modalName = $gift->name;
        $this->modalSlug = $gift->slug;
        
        // ФИКС: getRawOriginal берет чистое значение из БД, минуя аксессор!
        // Там лежит 'media/gifts/rose.webp', а не '/storage/...'
        $this->modalImageUrl = $gift->getRawOriginal('image_url'); 
        
        $this->modalPrice = $gift->price;
        $this->modalCategory = $gift->category;
        $this->modalIsActive = $gift->is_active;

        $this->resetValidation();
        $this->showGiftModal = true;
    }

        /**
     * Вычисляемое свойство для чистого URL в модалке.
     * Берет чистый путь из $modalImageUrl и делает из него ссылку.
     */
    #[Computed]
    public function previewImageUrl(): string
    {
        if (empty($this->modalImageUrl)) {
            return '';
        }

        // Если админ вставил внешний URL (http...)
        if (filter_var($this->modalImageUrl, FILTER_VALIDATE_URL)) {
            return $this->modalImageUrl;
        }

        // Иначе это путь к нашему файлу (media/gifts/...)
        return \Illuminate\Support\Facades\Storage::url($this->modalImageUrl);
    }

    public function saveGift(): void
    {
        $this->validate($this->giftRules());

        $gift = Gift::create([
            'name' => $this->modalName,
            'slug' => $this->modalSlug,
            'image_url' => $this->modalImageUrl,
            'price' => $this->modalPrice,
            'category' => $this->modalCategory,
            'is_active' => $this->modalIsActive,
        ]);

        AdminLog::record('gift.create', $gift, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Подарок добавлен в каталог!');
        $this->showGiftModal = false;
    }

    public function updateGift(): void
    {
        $this->validate($this->giftRules());

        $gift = Gift::find($this->editingGiftId);
        if (!$gift) return;

        $gift->update([
            'name' => $this->modalName,
            'slug' => $this->modalSlug,
            'image_url' => $this->modalImageUrl,
            'price' => $this->modalPrice,
            'category' => $this->modalCategory,
            'is_active' => $this->modalIsActive,
        ]);

        AdminLog::record('gift.update', $gift, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Подарок обновлен!');
        $this->showGiftModal = false;
    }

    public function deleteGift(int $id): void
    {
        $gift = Gift::find($id);
        if (!$gift) return;

        // Проверка: если подарок уже отправляли, жестко удалять его не стоит, лучше просто деактивировать.
        $sentCount = UserGift::where('gift_id', $gift->id)->count();
        if ($sentCount > 0) {
            $gift->update(['is_active' => false]);
            AdminLog::record('gift.deactivate', $gift, auth()->user());
            $this->dispatch('show-toast', type: 'warning', message: "Подарок был отправлен {$sentCount} раз. Он скрыт из продажи, но сохранен в БД.");
            return;
        }

        AdminLog::record('gift.delete', $gift, auth()->user());
        $gift->delete();
        $this->dispatch('show-toast', type: 'success', message: 'Подарок удален из каталога');
    }

    public function toggleGiftStatus(int $id): void
    {
        $gift = Gift::find($id);
        if (!$gift) return;

        $gift->update(['is_active' => !$gift->is_active]);
        AdminLog::record('gift.update', $gift, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: $gift->is_active ? 'Подарок в продаже' : 'Подарок скрыт');
    }

       protected function giftRules(): array
    {
        $slugRule = 'required|alpha_dash|unique:gifts,slug';
        if ($this->editingGiftId) {
            $slugRule .= ',' . $this->editingGiftId;
        }

        return [
            'modalName' => 'required|string|max:255',
            'modalSlug' => $slugRule,
            'modalImageUrl' => 'required|string|max:255',
            'modalPrice' => 'required|integer|min:1',
            // ФИКС: Строгая валидация через Enum
            'modalCategory' => ['required', new Enum(GiftCategory::class)],
            'modalIsActive' => 'boolean',
        ];
    }

    #[Computed]
    public function gifts()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return Gift::query()
            ->when($this->catalogSearch, function ($q) use ($operator) {
                $search = $this->catalogSearch;
                $q->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                      ->orWhere('slug', $operator, "%{$search}%");
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->when($this->categoryFilter !== 'all', fn($q) => $q->where('category', $this->categoryFilter))
            ->latest()
            ->paginate(15);
    }

    // ============================================
    // ИСТОРИЯ ОТПРАВЛЕННЫХ ПОДАРКОВ
    // ============================================

    public function updatedHistorySearch(): void { $this->resetPage(); }
    public function updatedPrivacyFilter(): void { $this->resetPage(); }

    public function deleteUserGift(int $id): void
    {
        $userGift = UserGift::find($id);
        if ($userGift) {
            AdminLog::record('user_gift.delete', $userGift, auth()->user());
            $userGift->delete(); // Soft delete
            $this->dispatch('show-toast', type: 'success', message : 'Подарок удален из профиля юзера');
        }
    }

    #[Computed]
    public function userGifts()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        
        // Хелпер для жадной загрузки юзеров с траншем (чтобы видеть удаленных)
        $userQuery = fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen', 'deleted_at')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);

        return UserGift::query()
            ->with([
                'sender' => $userQuery, 
                'receiver' => $userQuery, 
                'gift:id,name,image_url'
            ])
            ->when($this->historySearch, function ($q) use ($operator) {
                $search = $this->historySearch;
                $q->where(function ($q) use ($search, $operator) {
                    $q->whereHas('sender', fn($sq) => $sq->withTrashed()->where('name', $operator, "%{$search}%"))
                      ->orWhereHas('receiver', fn($rq) => $rq->withTrashed()->where('name', $operator, "%{$search}%"));
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->when($this->privacyFilter === 'public', fn($q) => $q->where('is_private', false))
            ->when($this->privacyFilter === 'private', fn($q) => $q->where('is_private', true))
            ->latest()
            ->paginate(15);
    }
}; 
?>

<div class="space-y-6">
    <!-- Шапка с кнопкой "Назад" -->
    <div class="flex items-center gap-4">
        @php
            $previousUrl = url()->previous();
            $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                ? $previousUrl 
                : route('admin.dashboard');
        @endphp

        <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
            <x-lucide-arrow-left class="w-5 h-5" />
        </a>
        <div>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-gift class="w-6 h-6" />
                Подарки и монетизация
            </h1>
            <p class="text-sm text-muted-foreground">Управление каталогом и финансовой историей</p>
        </div>
    </div>

    <!-- Вкладки -->
    <div class="border-b border-border">
        <nav class="flex gap-4 flex-wrap">
            <button wire:click="setTab('catalog')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'catalog' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-package class="w-4 h-4 inline mr-1" /> Каталог
            </button>
            <button wire:click="setTab('history')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'history' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-history class="w-4 h-4 inline mr-1" /> История отправлений
            </button>
        </nav>
    </div>

    <!-- ============================================ -->
    <!-- ВКЛАДКА: КАТАЛОГ                             -->
    <!-- ============================================ -->
    @if($activeTab === 'catalog')
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2">
                    <x-ui.select wire:model.live="categoryFilter" class="min-w-40">
                        <x-ui.select-trigger><x-ui.select-value placeholder="Категория" /></x-ui.select-trigger>
                        <x-ui.select-content>
                            <x-ui.select-item value="all">Все категории</x-ui.select-item>
                            @foreach(\App\Enums\GiftCategory::options() as $key => $cat)
                                <x-ui.select-item value="{{ $key }}">{{ $cat }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>
                    
                    <div class="relative w-64">
                        <x-ui.input wire:model.live.debounce.300ms="catalogSearch" type="search" placeholder="Поиск по названию или ID..." class="pl-9 pr-8" />
                        <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                        @if(!empty($catalogSearch))
                            <button wire:click="$set('catalogSearch', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><x-lucide-x class="w-4 h-4" /></button>
                        @endif
                    </div>
                </div>

                <x-ui.button wire:click="createGiftModal" variant="default" size="sm">
                    <x-lucide-plus class="w-4 h-4" /> Добавить подарок
                </x-ui.button>
            </div>

            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-12">ID</x-ui.table-head>
                        <x-ui.table-head class="w-16">Картинка</x-ui.table-head>
                        <x-ui.table-head>Название</x-ui.table-head>
                        <x-ui.table-head>Категория</x-ui.table-head>
                        <x-ui.table-head>Цена</x-ui.table-head>
                        <x-ui.table-head>Статус</x-ui.table-head>
                        <x-ui.table-head class="text-right">Действия</x-ui.table-row>
                    </x-ui.table-row>
                </x-ui.table-header>

                <x-ui.table-body>
                    @forelse ($this->gifts as $gift)
                        @php $catEnum = \App\Enums\GiftCategory::tryFrom($gift->category ?? ''); @endphp
                        <x-ui.table-row wire:key="gift-{{ $gift->id }}">
                            <x-ui.table-cell class="text-muted-foreground text-xs font-mono">
                                #{{ $gift->id }}
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($gift->image_url)
                                    <div class="w-14 h-14 overflow-hidden rounded-md bg-muted shrink-0">
                                        <img src="{{ $gift->image_url }}" alt="{{ $gift->name }}" class="w-full h-full object-cover">
                                    </div>
                                @else
                                    <div class="w-14 h-14 flex items-center justify-center rounded-md bg-muted border border-dashed border-border">
                                        <x-lucide-image-off class="w-4 h-4 text-muted-foreground/50" />
                                    </div>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                {{-- ФИКС: Название стало кликабельным --}}
                                <button wire:click="editGiftModal({{ $gift->id }})" class="text-left hover:text-primary transition-colors group">
                                    <div class="font-medium text-sm group-hover:underline">{{ $gift->name }}</div>
                                    <div class="text-xs text-muted-foreground font-mono">{{ $gift->slug }}</div>
                                </button>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($catEnum)
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium border {{ $catEnum->color() }}">
                                        {{ $catEnum->label() }}
                                    </span>
                                @else
                                    <x-ui.badge variant="outline" size="sm">Неизвестно</x-ui.badge>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="font-medium text-sm">
                                {{ number_format($gift->price, 0, ',', ' ') }} 💎
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <button wire:click="toggleGiftStatus({{ $gift->id }})" class="cursor-pointer" wire:loading.attr="disabled">
                                    @if($gift->is_active)
                                        <x-ui.badge variant="success" size="sm">В продаже</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" size="sm">Скрыт</x-ui.badge>
                                    @endif
                                </button>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-right">
                                <x-ui.dropdown-menu>
                                    <x-ui.dropdown-menu-trigger>
                                        <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                                    </x-ui.dropdown-menu-trigger>
                                    <x-ui.dropdown-menu-content align="end">
                                        <x-ui.dropdown-menu-item wire:click="editGiftModal({{ $gift->id }})">
                                            <x-lucide-pencil class="w-4 h-4" /> Редактировать
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteGift({{ $gift->id }})" wire:confirm="Удалить подарок из каталога? Если он уже отправлялся, он будет скрыт.">
                                            <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                                        </x-ui.dropdown-menu-item>
                                    </x-ui.dropdown-menu-content>
                                </x-ui.dropdown-menu>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="7" class="py-12 text-center text-muted-foreground">
                                <x-lucide-package-x class="w-12 h-12 opacity-30 mx-auto mb-2" />
                                <p>Каталог пуст</p>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            <div class="mt-4">{{ $this->gifts->links('partials.pagination') }}</div>
        </div>

    <!-- ============================================ -->
    <!-- ВКЛАДКА: ИСТОРИЯ ОТПРАВЛЕННЫХ ПОДАРКОВ       -->
    <!-- ============================================ -->
    @elseif($activeTab === 'history')
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex flex-wrap gap-1.5">
                    <x-ui.button wire:click="$set('privacyFilter', 'all')" variant="{{ $privacyFilter === 'all' ? 'default' : 'secondary' }}" size="sm">Все</x-ui.button>
                    <x-ui.button wire:click="$set('privacyFilter', 'public')" variant="{{ $privacyFilter === 'public' ? 'default' : 'secondary' }}" size="sm">Публичные</x-ui.button>
                    <x-ui.button wire:click="$set('privacyFilter', 'private')" variant="{{ $privacyFilter === 'private' ? 'default' : 'secondary' }}" size="sm">Приватные 🔒</x-ui.button>
                </div>

                <div class="relative w-64">
                    <x-ui.input wire:model.live.debounce.300ms="historySearch" type="search" placeholder="Поиск по имени или ID транзакции..." class="pl-9 pr-8" />
                    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    @if(!empty($historySearch))
                        <button wire:click="$set('historySearch', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><x-lucide-x class="w-4 h-4" /></button>
                    @endif
                </div>
            </div>

            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-12">ID Транз.</x-ui.table-head>
                        <x-ui.table-head>Отправитель</x-ui.table-head>
                        <x-ui.table-head></x-ui.table-head>
                        <x-ui.table-head>Получатель</x-ui.table-head>
                        <x-ui.table-head>Подарок (Снапшот)</x-ui.table-head>
                        <x-ui.table-head>Цена</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                        <x-ui.table-head class="text-right">Действия</x-ui.table-row>
                    </x-ui.table-row>
                </x-ui.table-header>

                <x-ui.table-body>
                    @forelse ($this->userGifts as $uGift)
                        <x-ui.table-row wire:key="ugift-{{ $uGift->id }}">
                            <x-ui.table-cell class="text-muted-foreground text-xs font-mono">
                                #{{ $uGift->id }}
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($uGift->sender)
                                    <a href="{{ route('admin.users.show', $uGift->sender->id) }}" wire:navigate class="flex items-center gap-2 group">
                                        <x-avatar src="{{ $uGift->sender->avatar_url }}" name="{{ $uGift->sender->name }}" size="sm" userId="{{ $uGift->sender->id }}" showStatus="true" :isOnline="$uGift->sender->is_online"/>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                                <x-user-status-sign :user="$uGift->sender" />
                                                {{ $uGift->sender->name }}
                                            </span>
                                            @if($uGift->sender->trashed()) <x-ui.badge variant="secondary" size="xs">Удален</x-ui.badge> @endif
                                        </div>
                                    </a>
                                @else
                                    <span class="text-xs text-muted-foreground italic">Система/Удален</span>
                                @endif
                            </x-ui.table-cell>
                            
                            <x-ui.table-cell class="text-muted-foreground"><x-lucide-arrow-right class="w-4 h-4" /></x-ui.table-cell>

                            <x-ui.table-cell>
                                @if($uGift->receiver)
                                    <a href="{{ route('admin.users.show', $uGift->receiver->id) }}" wire:navigate class="flex items-center gap-2 group">
                                        <x-avatar src="{{ $uGift->receiver->avatar_url }}" name="{{ $uGift->receiver->name }}" size="sm" userId="{{ $uGift->receiver->id }}" showStatus="true" :isOnline="$uGift->receiver->is_online"/>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                                <x-user-status-sign :user="$uGift->receiver" />
                                                {{ $uGift->receiver->name }}
                                            </span>
                                            @if($uGift->receiver->trashed()) <x-ui.badge variant="secondary" size="xs">Удален</x-ui.badge> @endif
                                        </div>
                                    </a>
                                @else
                                    <span class="text-xs text-muted-foreground italic">Удален</span>
                                @endif
                            </x-ui.table-cell>

                            <x-ui.table-cell>
                                <div class="flex items-center gap-2">
                                    {{-- ФИКС: Размер картинки 14x14 как в каталоге --}}
                                    <div class="w-14 h-14 overflow-hidden rounded-md bg-muted shrink-0">
                                        <img src="{{ $uGift->image_url }}" alt="{{ $uGift->snapshot_name }}" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">{{ $uGift->snapshot_name }}</div>
                                        @if($uGift->is_private)
                                            <x-ui.badge variant="warning" size="xs" class="mt-0.5">Приватный</x-ui.badge>
                                        @endif
                                    </div>
                                </div>
                            </x-ui.table-cell>

                            <x-ui.table-cell class="text-sm text-muted-foreground font-medium">
                                {{ number_format($uGift->snapshot_price, 0, ',', ' ') }} 💎
                            </x-ui.table-cell>

                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $uGift->created_at->format('d.m.Y H:i') }}
                            </x-ui.table-cell>

                            <x-ui.table-cell class="text-right">
                                <x-ui.dropdown-menu>
                                    <x-ui.dropdown-menu-trigger>
                                        <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                                    </x-ui.dropdown-menu-trigger>
                                    <x-ui.dropdown-menu-content align="end">
                                        @if($uGift->message)
                                            <x-ui.dropdown-menu-label class="max-w-xs truncate">Сообщение: "{{ $uGift->message }}"</x-ui.dropdown-menu-label>
                                            <x-ui.dropdown-menu-separator />
                                        @endif
                                        <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteUserGift({{ $uGift->id }})" wire:confirm="Убрать подарок из профиля юзера? (Останется в логах)">
                                            <x-lucide-trash-2 class="w-4 h-4" /> Убрать из профиля
                                        </x-ui.dropdown-menu-item>
                                    </x-ui.dropdown-menu-content>
                                </x-ui.dropdown-menu>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                                <x-lucide-inbox class="w-12 h-12 opacity-30 mx-auto mb-2" />
                                <p>История пуста</p>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            <div class="mt-4">{{ $this->userGifts->links('partials.pagination') }}</div>
        </div>
    @endif
    <!-- МОДАЛКА СОЗДАНИЯ/РЕДАКТИРОВАНИЯ ПОДАРКА -->
    <div x-data x-show="$wire.showGiftModal" 
         x-cloak
         x-transition.opacity
         @click.self="$wire.showGiftModal = false"
         @keydown.escape.window="$wire.showGiftModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
         
        <div x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="relative bg-card border border-border rounded-lg shadow-2xl max-w-xl w-full mx-4 overflow-hidden">
             
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">{{ $editingGiftId ? 'Редактировать подарок' : 'Добавить подарок' }}</h2>
                <x-ui.button variant="ghost" size="icon-sm" @click="$wire.showGiftModal = false">
                    <x-lucide-x class="w-5 h-5" />
                </x-ui.button>
            </div>

            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-2">
                    <x-ui.label for="g-name">Название</x-ui.label>
                    {{-- ФИКС: .blur вместо .live, чтобы не было запросов на каждую букву --}}
                    <x-ui.input id="g-name" wire:model.blur="modalName" placeholder="Красная роза" />
                    @error('modalName') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="g-slug">Slug (URL)</x-ui.label>
                    <x-ui.input id="g-slug" wire:model="modalSlug" placeholder="red_rose" />
                    @error('modalSlug') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                </div>

                {{-- ФИКС: items-stretch заставляет колонки быть одинаковой высоты --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-stretch">

                    {{-- ЛЕВАЯ КОЛОНКА: Картинка (flex-1 заставляет блок занять всю высоту) --}}
                    <div class="flex flex-col gap-2">                    
                        <x-ui.label>Изображение подарка</x-ui.label>
                        
                        {{-- Контейнер всегда растягивается (flex-1). Классы не меняются, поэтому высота не прыгает! --}}
                        <div class="min-h-[7rem] flex-1 flex flex-col items-center justify-center gap-3 border border-dashed border-border rounded-lg p-3 bg-muted/10 text-center">
                            
                            @if($modalImageUrl)
                                <div class="flex items-center gap-4 w-full ">
                                    <div class="w-22 h-22 bg-background rounded-lg overflow-hidden border border-border shrink-0">
                                        <img src="{{ $this->previewImageUrl }}" class="w-full h-full object-cover" alt="Preview">
                                    </div>
                                    <div class="flex flex-col gap-4 flex-1">
                                        <x-ui.button type="button" wire:click="$dispatch('open-media-manager', { collection: 'gifts' })" variant="outline" size="sm" class="w-full gap-1.5">
                                            <x-lucide-refresh-cw class="w-3.5 h-3.5" /> Заменить
                                        </x-ui.button>
                                        <x-ui.button type="button" wire:click="$set('modalImageUrl', '')" variant="destructive" size="sm" class="w-full gap-1.5">
                                            <x-lucide-trash-2 class="w-3.5 h-3.5" /> Удалить
                                        </x-ui.button>
                                    </div>
                                </div>
                            @else
                                <x-lucide-image-plus class="w-8 h-8 text-muted-foreground/50" />
                                <x-ui.button type="button" wire:click="$dispatch('open-media-manager', { collection: 'gifts' })" variant="secondary" size="sm" class="gap-1.5">
                                    <x-lucide-folder-open class="w-3.5 h-3.5" /> Выбрать из хранилища
                                </x-ui.button>
                            @endif
                        </div>
                        
                        @error('modalImageUrl') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- ПРАВАЯ КОЛОНКА: Поля --}}
                    <div class="flex flex-col justify-between gap-2.5">
                        <div class="flex flex-col gap-2">
                            <x-ui.label for="g-price">Цена (Кредиты)</x-ui.label>
                            <x-ui.input id="g-price" wire:model="modalPrice" type="number" min="1" />
                            @error('modalPrice') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex flex-col gap-2">
                            <x-ui.label>Категория</x-ui.label>
                            <x-ui.select wire:model="modalCategory">
                                <x-ui.select-trigger class="w-full"><x-ui.select-value /></x-ui.select-trigger>
                                <x-ui.select-content>
                                    @foreach(\App\Enums\GiftCategory::options() as $key => $cat)
                                        <x-ui.select-item value="{{ $key }}">{{ $cat }}</x-ui.select-item>
                                    @endforeach
                                </x-ui.select-content>
                            </x-ui.select>
                        </div>
                    </div>
                </div>

                {{-- Тумблер активности --}}
                <div class="flex items-center gap-3 pt-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="modalIsActive" class="sr-only peer" />
                        <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                    </label>
                    <span class="text-sm font-medium">В продаже</span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showGiftModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                
                @if(!$editingGiftId)
                    {{-- КНОПКА СОЗДАНИЯ --}}
                    <x-ui.button wire:click="saveGift" variant="default" size="sm" wire:loading.attr="disabled" class="gap-2">
                        <x-lucide-loader-circle class="w-4 h-4 animate-spin" wire:loading />
                        <x-lucide-save class="w-4 h-4" wire:loading.remove />
                        <span wire:loading.remove>Сохранить</span>
                        <span wire:loading>Сохранение...</span>
                    </x-ui.button>
                @else
                    {{-- КНОПКА РЕДАКТИРОВАНИЯ --}}
                    <x-ui.button wire:click="updateGift" variant="default" size="sm" wire:loading.attr="disabled" class="gap-2">
                        <x-lucide-loader-circle class="w-4 h-4 animate-spin" wire:loading />
                        <x-lucide-save class="w-4 h-4" wire:loading.remove />
                        <span wire:loading.remove>Сохранить</span>
                        <span wire:loading>Сохранение...</span>
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>
    <livewire:admin.media-manager />
</div>