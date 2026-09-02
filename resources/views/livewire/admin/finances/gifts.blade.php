<?php

use App\Actions\Admin\ManageGiftsAction;
use App\Models\Gift;
use App\Models\UserGift;
use App\Enums\GiftCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public bool $isSelectingMedia = false;
    public string $activeTab = 'catalog';
    
    #[Url(as: 'catalog_search', except: '')]
    public string $catalogSearch = '';
    
    public string $categoryFilter = 'all';

    #[Url(as: 'history_search', except: '')]
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

    public string $backUrl = '';
  
    public function mount(): void
    {
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        if (request()->has('history_search')) {
            $this->activeTab = 'history';
            $this->privacyFilter = 'all';
            return;
        }
        
        if (request()->has('catalog_search')) {
            $this->activeTab = 'catalog';
            $this->categoryFilter = 'all';
            return;
        }

        $this->activeTab = session('admin_gifts_tab', 'catalog');
    }

    #[Computed]
    public function historyCounts(): array
    {
        $baseQuery = UserGift::withTrashed();
        return [
            'all' => (clone $baseQuery)->count(),
            'public' => (clone $baseQuery)->where('is_private', false)->count(),
            'private' => (clone $baseQuery)->where('is_private', true)->count(),
        ];
    }

    #[On('media-selected')]
    public function setMediaFromManager(int $mediaId, string $diskPath, string $collection): void
    {
        if ($collection === 'gift') {
            $this->modalImageUrl = $diskPath;
        }
        $this->isSelectingMedia = false;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->catalogSearch = '';
        $this->historySearch = '';
        session(['admin_gifts_tab' => $tab]);
        $this->resetPage();
        
        unset($this->gifts);
        unset($this->userGifts);
        unset($this->tabCounts);
        unset($this->historyCounts);
    }

    #[Computed]
    public function tabCounts(): array
    {
        return [
            'catalog' => Gift::count(),
            'history' => UserGift::withTrashed()->count(),
        ];
    }

    public function updatedCatalogSearch(): void { $this->resetPage(); unset($this->gifts); }
    public function updatedCategoryFilter(): void { $this->resetPage(); unset($this->gifts); }
    
    public function clearCatalogSearch(): void
    {
        $this->catalogSearch = '';
        $this->resetPage();
        unset($this->gifts);
    }

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
        $this->modalImageUrl = $gift->getRawOriginal('image_url'); 
        $this->modalPrice = $gift->price;
        $this->modalCategory = $gift->category;
        $this->modalIsActive = $gift->is_active;

        $this->resetValidation();
        $this->showGiftModal = true;
    }

    #[Computed]
    public function previewImageUrl(): string
    {
        if (empty($this->modalImageUrl)) return '';
        if (filter_var($this->modalImageUrl, FILTER_VALIDATE_URL)) {
            return $this->modalImageUrl;
        }
        return \Illuminate\Support\Facades\Storage::url($this->modalImageUrl);
    }

    public function saveGift(ManageGiftsAction $action): void
    {
        $this->validate($this->giftRules());

        $data = [
            'name' => $this->modalName,
            'slug' => $this->modalSlug,
            'image_url' => $this->modalImageUrl,
            'price' => $this->modalPrice,
            'category' => $this->modalCategory,
            'is_active' => $this->modalIsActive,
        ];

        $action->createGift($data, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: 'Подарок добавлен в каталог!');
        $this->showGiftModal = false;
        unset($this->gifts);
        unset($this->tabCounts);
    }

    public function updateGift(ManageGiftsAction $action): void
    {
        $this->validate($this->giftRules());

        $gift = Gift::find($this->editingGiftId);
        if (!$gift) return;

        $data = [
            'name' => $this->modalName,
            'slug' => $this->modalSlug,
            'image_url' => $this->modalImageUrl,
            'price' => $this->modalPrice,
            'category' => $this->modalCategory,
            'is_active' => $this->modalIsActive,
        ];

        $action->updateGift($gift, $data, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: 'Подарок обновлен!');
        $this->showGiftModal = false;
        unset($this->gifts);
    }

    public function deleteGift(int $id, ManageGiftsAction $action): void
    {
        $gift = Gift::find($id);
        if (!$gift) return;

        $sentCount = UserGift::where('gift_id', $gift->id)->count();
        $isDeleted = $action->deleteGift($gift, auth()->user());

        if (!$isDeleted && $sentCount > 0) {
            $this->dispatch('show-toast', type: 'warning', message: "Подарок был отправлен {$sentCount} раз. Он скрыт из продажи, но сохранен в БД.");
        } else {
            $this->dispatch('show-toast', type: 'success', message: 'Подарок удален из каталога');
        }
        
        unset($this->gifts);
        unset($this->tabCounts);
    }

    public function toggleGiftStatus(int $id, ManageGiftsAction $action): void
    {
        $gift = Gift::find($id);
        if (!$gift) return;

        $action->toggleStatus($gift, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: $gift->fresh()->is_active ? 'Подарок в продаже' : 'Подарок скрыт');
        unset($this->gifts);
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
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);
    }

    public function updatedHistorySearch(): void { $this->resetPage(); unset($this->userGifts); }
    public function updatedPrivacyFilter(): void { $this->resetPage(); unset($this->userGifts); }
    
    public function clearHistorySearch(): void
    {
        $this->historySearch = '';
        $this->resetPage();
        unset($this->userGifts);
    }

    public function deleteUserGift(int $id, ManageGiftsAction $action): void
    {
        $userGift = UserGift::find($id);
        if (!$userGift) return;

        $action->hideUserGift($userGift, auth()->user());

        $this->dispatch('show-toast', type: 'success', message : 'Подарок отозван и скрыт из профиля юзера');
        unset($this->userGifts);
        unset($this->historyCounts);
    }

    public function restoreUserGift(int $id, ManageGiftsAction $action): void
    {
        $userGift = UserGift::withTrashed()->find($id);
        if (!$userGift) return;

        $action->restoreUserGift($userGift, auth()->user());

        $this->dispatch('show-toast', type: 'success', message : 'Подарок возвращен в профиль юзера');
        unset($this->userGifts);
        unset($this->historyCounts);
    }

    #[Computed]
    public function userGifts()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        
        $userQuery = fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen', 'deleted_at')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);

        return UserGift::query()
            ->withTrashed()
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
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);
    }
}; 
?>

<div class="space-y-6">
    <!-- Шапка с кнопкой "Назад" -->
        <div class="flex items-center gap-4">
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
            <button wire:click="setTab('catalog')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'catalog' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-package class="w-4 h-4" /> Каталог 
                <x-ui.badge variant="{{ $activeTab === 'catalog' ? 'default' : 'secondary' }}" size="xs">{{ $this->tabCounts['catalog'] }}</x-ui.badge>
            </button>
            <button wire:click="setTab('history')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'history' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-history class="w-4 h-4" /> История дарений 
                <x-ui.badge variant="{{ $activeTab === 'history' ? 'default' : 'secondary' }}" size="xs">{{ $this->tabCounts['history'] }}</x-ui.badge>
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
                        <button wire:click="clearCatalogSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><x-lucide-x class="w-4 h-4" /></button>
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
                        <x-ui.table-head class="text-right">Действия</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>

                <x-ui.table-body>
                    @forelse ($this->gifts as $gift)
                        @php $catEnum = \App\Enums\GiftCategory::tryFrom($gift->category ?? ''); @endphp
                        <x-ui.table-row wire:key="gift-{{ $gift->id }}-{{ $gift->is_active }}">
                            <x-ui.table-cell class="text-muted-foreground text-xs font-mono">
                                #{{ $gift->id }}
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($gift->image_url)
                                    <div class="w-14 h-14 overflow-hidden rounded-md bg-muted shrink-0">
                                        <x-media-image src="{{ $gift->image_url }}" alt="{{ $gift->name }}" class="w-full h-full object-cover"/>
                                    </div>
                                @else
                                    <div class="w-14 h-14 flex items-center justify-center rounded-md bg-muted border border-dashed border-border">
                                        <x-lucide-image-off class="w-4 h-4 text-muted-foreground/50" />
                                    </div>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
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

                                        @if($gift->is_active)
                                            <x-ui.dropdown-menu-item wire:click="toggleGiftStatus({{ $gift->id }})">
                                                <x-lucide-eye-off class="w-4 h-4 text-yellow-500" /> Убрать из продажи
                                            </x-ui.dropdown-menu-item>
                                        @else
                                            <x-ui.dropdown-menu-item wire:click="toggleGiftStatus({{ $gift->id }})">
                                                <x-lucide-check class="w-4 h-4 text-green-500" /> Вернуть в продажу
                                            </x-ui.dropdown-menu-item>
                                        @endif

                                        <x-ui.dropdown-menu-separator />

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
    <!-- ВКЛАДКА: ИСТОРИЯ ДАРЕНИЙ                     -->
    <!-- ============================================ -->
    @elseif($activeTab === 'history')
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex flex-wrap gap-1.5">
                    <x-ui.button wire:click="$set('privacyFilter', 'all')" variant="{{ $privacyFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                        <x-lucide-list class="w-4 h-4 inline mr-1" /> Все <x-ui.badge size="xs" class="ml-1">{{ $this->historyCounts['all'] }}</x-ui.badge>
                    </x-ui.button>
                    <x-ui.button wire:click="$set('privacyFilter', 'public')" variant="{{ $privacyFilter === 'public' ? 'default' : 'secondary' }}" size="sm">
                        <x-lucide-eye class="w-4 h-4 inline mr-1" /> Публичные <x-ui.badge size="xs" class="ml-1">{{ $this->historyCounts['public'] }}</x-ui.badge>
                    </x-ui.button>
                    <x-ui.button wire:click="$set('privacyFilter', 'private')" variant="{{ $privacyFilter === 'private' ? 'default' : 'secondary' }}" size="sm">
                        <x-lucide-lock class="w-4 h-4 inline mr-1" /> Приватные <x-ui.badge size="xs" variant="warning" class="ml-1">{{ $this->historyCounts['private'] }}</x-ui.badge>
                    </x-ui.button>
                </div>

                <div class="relative w-64">
                    <x-ui.input wire:model.live.debounce.300ms="historySearch" type="search" placeholder="Поиск по имени или ID..." class="pl-9 pr-8" />
                    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                  @if(!empty($historySearch))
                        <button wire:click="clearHistorySearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><x-lucide-x class="w-4 h-4" /></button>
                    @endif
                </div>
            </div>

            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-12 ">ID Тр.</x-ui.table-head>
                        <x-ui.table-head>Отправитель</x-ui.table-head>
                        <x-ui.table-head></x-ui.table-head>
                        <x-ui.table-head>Получатель</x-ui.table-head>
                        <x-ui.table-head>Подарок (Снапшот)</x-ui.table-head>
                        <x-ui.table-head>Цена</x-ui.table-head>                      
                        <x-ui.table-head>Статус</x-ui.table-head>
                        <x-ui.table-head class="min-w-[200px]">Сообщение</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                        <x-ui.table-head class="text-right">Действия</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>

                <x-ui.table-body>
                 @forelse ($this->userGifts as $uGift)
                    @php $isHighlighted = is_numeric($this->historySearch) && $uGift->id == (int)$this->historySearch; @endphp
                    <x-ui.table-row wire:key="ugift-{{ $uGift->id }}-{{ $uGift->trashed() ? 'hidden' : 'visible' }}" 
                        class="{{ $isHighlighted ? 'bg-blue-500/10 ring-2 ring-blue-500/50' : '' }}"
                        x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                        x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                    >
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
                                                @if($uGift->sender->has_active_premium)<x-lucide-crown class="w-3.5 h-3.5 text-yellow-500" />@endif                              
                                            </span>
                                            <!-- ФИКС: Добавили email -->
                                            <span class="text-xs text-muted-foreground truncate">{{ $uGift->sender->email }}</span>                                        
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
                                                @if($uGift->receiver->has_active_premium)<x-lucide-crown class="w-3.5 h-3.5 text-yellow-500" />@endif                              
                                            </span>
                                            <!-- ФИКС: Добавили email -->
                                            <span class="text-xs text-muted-foreground truncate">{{ $uGift->receiver->email }}</span>                                            
                                        </div>
                                    </a>
                                @else
                                    <span class="text-xs text-muted-foreground italic">Удален</span>
                                @endif
                            </x-ui.table-cell>

                            <x-ui.table-cell>
                                <div class="flex items-center gap-2">
                                    <div class="w-14 h-14 overflow-hidden rounded-md bg-muted shrink-0 flex items-center justify-center">
                                        @php $imgSrc = $uGift->snapshot_image_url ?? $uGift->gift?->image_url; @endphp
                                        @if($imgSrc)
                                            <x-media-image src="{{ $imgSrc }}" alt="{{ $uGift->snapshot_name }}" class="w-full h-full object-cover"/>
                                        @else
                                            <x-lucide-image-off class="w-4 h-4 text-muted-foreground/50" />
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium truncate">{{ $uGift->snapshot_name }}</div>
                                        @if($uGift->is_private)
                                            <x-ui.badge variant="warning" size="xs" class="mt-0.5">Приватный</x-ui.badge>
                                        @endif
                                    </div>
                                </div>
                            </x-ui.table-cell>

                            <x-ui.table-cell class="text-sm text-muted-foreground font-medium">
                                {{ number_format($uGift->snapshot_price, 0, ',', ' ') }} 💎
                            </x-ui.table-cell>

                            <x-ui.table-cell>
                                @if($uGift->trashed())
                                    <x-ui.badge variant="destructive" size="sm">Скрыт</x-ui.badge>
                                @else
                                    <x-ui.badge variant="success" size="sm">В профиле</x-ui.badge>
                                @endif
                            </x-ui.table-cell>

                            <x-ui.table-cell class="max-w-[12rem]">
                                @if($uGift->message)
                                    <p class="text-xs max-w-[12rem] italic line-clamp-2 text-muted-foreground" title="{{ $uGift->message }}">
                                        "{{ $uGift->message }}"
                                    </p>
                                @else
                                    <span class="text-xs text-muted-foreground/50">—</span>
                                @endif
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
                                        
                                        @if($uGift->trashed())
                                            {{-- Если подарок скрыт, предлагаем вернуть --}}
                                            <x-ui.alert-dialog>
                                                <x-ui.alert-dialog-trigger asChild>
                                                    <button class="relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground hover:bg-accent hover:text-accent-foreground text-green-600 w-full">
                                                        <x-lucide-rotate-ccw class="w-4 h-4 mr-2" /> Вернуть в профиль получателя
                                                    </button>
                                                </x-ui.alert-dialog-trigger>
                                                <x-ui.alert-dialog-content>
                                                    <x-ui.alert-dialog-header>
                                                        <x-ui.alert-dialog-title>Вернуть подарок в профиль?</x-ui.alert-dialog-title>
                                                        <x-ui.alert-dialog-description>
                                                            Подарок «{{ $uGift->snapshot_name }}» снова станет виден в анкете получателя.
                                                        </x-ui.alert-dialog-description>
                                                    </x-ui.alert-dialog-header>
                                                    <x-ui.alert-dialog-footer>
                                                        <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                                                        <x-ui.alert-dialog-action wire:click="restoreUserGift({{ $uGift->id }})">Вернуть</x-ui.alert-dialog-action>
                                                    </x-ui.alert-dialog-footer>
                                                </x-ui.alert-dialog-content>
                                            </x-ui.alert-dialog>
                                        @else
                                            {{-- Если подарок виден, предлагаем скрыть --}}
                                            <x-ui.alert-dialog>
                                                <x-ui.alert-dialog-trigger asChild>
                                                    <button class="relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground hover:bg-accent hover:text-accent-foreground text-destructive w-full">
                                                        <x-lucide-eye-off class="w-4 h-4 mr-2" /> Скрыть из профиля получателя
                                                    </button>
                                                </x-ui.alert-dialog-trigger>
                                                <x-ui.alert-dialog-content>
                                                    <x-ui.alert-dialog-header>
                                                        <x-ui.alert-dialog-title>Скрыть подарок из профиля?</x-ui.alert-dialog-title>
                                                        <x-ui.alert-dialog-description>
                                                            Подарок «{{ $uGift->snapshot_name }}» будет скрыт из анкеты получателя, но останется в истории дарений (в логах) для СБ. Кредиты отправителю не возвращаются.
                                                        </x-ui.alert-dialog-description>
                                                    </x-ui.alert-dialog-header>
                                                    <x-ui.alert-dialog-footer>
                                                        <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                                                        <x-ui.alert-dialog-action wire:click="deleteUserGift({{ $uGift->id }})">Скрыть</x-ui.alert-dialog-action>
                                                    </x-ui.alert-dialog-footer>
                                                </x-ui.alert-dialog-content>
                                            </x-ui.alert-dialog>
                                        @endif

                                    </x-ui.dropdown-menu-content>
                                </x-ui.dropdown-menu>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="10" class="py-12 text-center text-muted-foreground">
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
                        <x-ui.input id="g-name" wire:model.blur="modalName" placeholder="Красная роза" />
                        @error('modalName') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-ui.label for="g-slug">Slug (URL)</x-ui.label>
                        <x-ui.input id="g-slug" wire:model="modalSlug" placeholder="red_rose" />
                        @error('modalSlug') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-stretch">
                    <div class="flex flex-col gap-2">                    
                        <x-ui.label>Изображение подарка</x-ui.label>
                        
                        <div class="min-h-[7rem] flex-1 flex flex-col items-center justify-center gap-3 border border-dashed border-border rounded-lg p-3 bg-muted/10 text-center">
                            
                            @if($isSelectingMedia)
                                <x-lucide-loader-2 class="w-8 h-8 text-primary animate-spin" />
                                <p class="text-xs text-muted-foreground">Выбор изображения...</p>
                            @elseif($modalImageUrl)
                                <div class="flex items-center gap-4 w-full">
                                    <div class="w-22 h-22 bg-background rounded-lg overflow-hidden border border-border shrink-0">
                                        <img src="{{ $this->previewImageUrl }}" class="w-full h-full object-cover" alt="Preview">
                                    </div>
                                    <div class="flex flex-col gap-4 flex-1">
                                        <x-ui.button type="button" wire:click="$set('isSelectingMedia', true); $dispatch('open-media-manager', { collection: 'gift' })" variant="outline" size="sm" class="w-full gap-1.5">
                                            <x-lucide-refresh-cw class="w-3.5 h-3.5" /> Заменить
                                        </x-ui.button>
                                        <x-ui.button type="button" wire:click="$set('modalImageUrl', '')" variant="destructive" size="sm" class="w-full gap-1.5">
                                            <x-lucide-trash-2 class="w-3.5 h-3.5" /> Удалить
                                        </x-ui.button>
                                    </div>
                                </div>
                            @else
                                <x-lucide-image-plus class="w-8 h-8 text-muted-foreground/50" />
                                <x-ui.button type="button" wire:click="$set('isSelectingMedia', true); $dispatch('open-media-manager', { collection: 'gift' })" variant="secondary" size="sm" class="gap-1.5">
                                    <x-lucide-folder-open class="w-3.5 h-3.5" /> Выбрать из хранилища
                                </x-ui.button>
                            @endif
                        </div>
                        
                        @error('modalImageUrl') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

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
                    <x-ui.button wire:click="saveGift" variant="default" size="sm" wire:loading.attr="disabled" class="gap-2">
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" wire:loading />
                        <x-lucide-save class="w-4 h-4" wire:loading.remove />
                        <span wire:loading.remove>Сохранить</span>
                        <span wire:loading>Сохранение...</span>
                    </x-ui.button>
                @else
                    <x-ui.button wire:click="updateGift" variant="default" size="sm" wire:loading.attr="disabled" class="gap-2">
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" wire:loading />
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