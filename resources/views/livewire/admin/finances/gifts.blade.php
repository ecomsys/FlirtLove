<?php

use App\Models\AdminLog;
use App\Models\Gift;
use App\Models\UserGift;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $activeTab = 'catalog';
    
    // === Фильтры каталога ===
    public string $catalogSearch = '';
    public string $categoryFilter = 'all';

    // === Фильтры истории ===
    public string $historySearch = '';
    public string $privacyFilter = 'all';

    // === Поля формы Каталога ===
    public bool $showGiftModal = false;
    public ?int $editingGiftId = null;
    public string $modalName = '';
    public string $modalSlug = '';
    public string $modalImageUrl = '';
    public int $modalPrice = 100;
    public string $modalCategory = 'romantic';
    public bool $modalIsActive = true;

    private array $categoriesList = [
        'romantic' => 'Романтика',
        'cars' => 'Авто',
        'male' => 'Для него',
        'female' => 'Для неё',
        '18+' => '18+',
        'fun' => 'Приколы',
    ];

    public function mount(): void
    {
        $this->activeTab = session('admin_gifts_tab', 'catalog');
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
        $this->modalImageUrl = $gift->image_url;
        $this->modalPrice = $gift->price;
        $this->modalCategory = $gift->category;
        $this->modalIsActive = $gift->is_active;

        $this->resetValidation();
        $this->showGiftModal = true;
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
        if ($gift) {
            AdminLog::record('gift.delete', $gift, auth()->user());
            $gift->delete();
            $this->dispatch('show-toast', type: 'success', message: 'Подарок удален из каталога');
        }
    }

    public function toggleGiftStatus(int $id): void
    {
        $gift = Gift::find($id);
        if ($gift) {
            $gift->update(['is_active' => !$gift->is_active]);
            AdminLog::record('gift.update', $gift, auth()->user());
        }
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
            'modalCategory' => 'required|string',
            'modalIsActive' => 'boolean',
        ];
    }

    #[Computed]
    public function gifts()
    {
        return Gift::query()
            ->when($this->catalogSearch, fn($q) => $q->where('name', 'like', "%{$this->catalogSearch}%"))
            ->when($this->categoryFilter !== 'all', fn($q) => $q->where('category', $this->categoryFilter))
            ->latest()
            ->paginate(15);
    }

    // ============================================
    // ИСТОРИЯ ОТПРАВЛЕННЫХ ПОДАРКОВ
    // ============================================

    public function updatedHistorySearch(): void { $this->resetPage(); }
    public function updatedPrivacyFilter(): void { $this->resetPage(); }

    /**
     * Удалить подарок из профиля юзера (Soft Delete)
     */
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
        return UserGift::query()
            ->with(['sender', 'receiver', 'gift'])
            ->when($this->historySearch, function ($q) {
                $search = strtolower($this->historySearch);
                $q->whereHas('sender', fn($q) => $q->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('receiver', fn($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->when($this->privacyFilter === 'public', fn($q) => $q->where('is_private', false))
            ->when($this->privacyFilter === 'private', fn($q) => $q->where('is_private', true))
            ->latest()
            ->paginate(15);
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-gift class="w-6 h-6" />
            Подарки и монетизация
        </h1>
    </div>

    <!-- Вкладки -->
    <div class="flex border-b border-border">
        <button wire:click="setTab('catalog')" class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px {{ $activeTab === 'catalog' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            Каталог
        </button>
        <button wire:click="setTab('history')" class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px {{ $activeTab === 'history' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            История отправлений
        </button>
    </div>

    <!-- ============================================ -->
    <!-- ВКЛАДКА: КАТАЛОГ                             -->
    <!-- ============================================ -->
    @if($activeTab === 'catalog')
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <x-ui.select wire:model.live="categoryFilter" class="min-w-40">
                        <x-ui.select-trigger><x-ui.select-value placeholder="Категория" /></x-ui.select-trigger>
                        <x-ui.select-content>
                            <x-ui.select-item value="all">Все категории</x-ui.select-item>
                            @foreach($this->categoriesList as $key => $cat)
                                <x-ui.select-item value="{{ $key }}">{{ $cat }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>

                    <div class="relative w-64">
                        <x-ui.input wire:model.live.debounce.300ms="catalogSearch" type="search" placeholder="Поиск по названию..." class="pl-9 pr-8" />
                        <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    </div>
                </div>

                <x-ui.button wire:click="createGiftModal" variant="default" size="sm">
                    <x-lucide-plus class="w-4 h-4" /> Добавить подарок
                </x-ui.button>
            </div>

            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">Картинка</x-ui.table-head>
                        <x-ui.table-head>Название</x-ui.table-head>
                        <x-ui.table-head>Категория</x-ui.table-head>
                        <x-ui.table-head>Цена (кредиты)</x-ui.table-head>
                        <x-ui.table-head>Статус</x-ui.table-head>
                        <x-ui.table-head class="text-right">Действия</x-ui.table-row>
                    </x-ui.table-row>
                </x-ui.table-header>

                <x-ui.table-body>
                    @forelse ($this->gifts as $gift)
                        <x-ui.table-row wire:key="gift-{{ $gift->id }}">
                            <x-ui.table-cell>
                                <img src="{{ $gift->image_url }}" alt="{{ $gift->name }}" class="w-10 h-10 object-contain rounded bg-muted p-1">
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="font-medium text-sm">{{ $gift->name }}</div>
                                <div class="text-xs text-muted-foreground">{{ $gift->slug }}</div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge variant="outline" size="sm">{{ $this->categoriesList[$gift->category] ?? $gift->category }}</x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="font-medium text-sm">
                                {{ $gift->price }} 💎
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <button wire:click="toggleGiftStatus({{ $gift->id }})" class="cursor-pointer">
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
                                        <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteGift({{ $gift->id }})" wire:confirm="Удалить подарок из каталога?">
                                            <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                                        </x-ui.dropdown-menu-item>
                                    </x-ui.dropdown-menu-content>
                                </x-ui.dropdown-menu>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
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
            <div class="flex items-center justify-between gap-3">
                <div class="flex flex-wrap gap-1.5">
                    <x-ui.button wire:click="$set('privacyFilter', 'all')" variant="{{ $privacyFilter === 'all' ? 'default' : 'secondary' }}" size="sm">Все</x-ui.button>
                    <x-ui.button wire:click="$set('privacyFilter', 'public')" variant="{{ $privacyFilter === 'public' ? 'default' : 'secondary' }}" size="sm">Публичные</x-ui.button>
                    <x-ui.button wire:click="$set('privacyFilter', 'private')" variant="{{ $privacyFilter === 'private' ? 'default' : 'secondary' }}" size="sm">Приватные 🔒</x-ui.button>
                </div>

                <div class="relative w-64">
                    <x-ui.input wire:model.live.debounce.300ms="historySearch" type="search" placeholder="Поиск по юзерам..." class="pl-9 pr-8" />
                    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                </div>
            </div>

            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
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
                            <x-ui.table-cell>
                                @if($uGift->sender)
                                    <div class="flex items-center gap-2">
                                        <x-avatar src="{{ $uGift->sender->avatar_url }}" name="{{ $uGift->sender->name }}" size="sm" />
                                        <span class="text-sm font-medium">{{ $uGift->sender->name }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-muted-foreground">Удален</span>
                                @endif
                            </x-ui.table-cell>
                            
                            <x-ui.table-cell class="text-muted-foreground"><x-lucide-arrow-right class="w-4 h-4" /></x-ui.table-cell>

                            <x-ui.table-cell>
                                @if($uGift->receiver)
                                    <div class="flex items-center gap-2">
                                        <x-avatar src="{{ $uGift->receiver->avatar_url }}" name="{{ $uGift->receiver->name }}" size="sm" />
                                        <span class="text-sm font-medium">{{ $uGift->receiver->name }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-muted-foreground">Удален</span>
                                @endif
                            </x-ui.table-cell>

                            <x-ui.table-cell>
                                <div class="flex items-center gap-2">
                                    <img src="{{ $uGift->image_url }}" alt="{{ $uGift->snapshot_name }}" class="w-8 h-8 object-contain rounded bg-muted p-0.5">
                                    <div>
                                        <div class="text-sm font-medium">{{ $uGift->snapshot_name }}</div>
                                        @if($uGift->is_private)
                                            <x-ui.badge variant="secondary" size="xs" class="mt-0.5">Приватный</x-ui.badge>
                                        @endif
                                    </div>
                                </div>
                            </x-ui.table-cell>

                            <x-ui.table-cell class="text-sm text-muted-foreground">
                                {{ $uGift->snapshot_price }} 💎
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
                                            <x-ui.dropdown-menu-item wire:click="">
                                                <x-lucide-message-square class="w-4 h-4" /> Сообщение: "{{ $uGift->message }}"
                                            </x-ui.dropdown-menu-item>
                                        @endif
                                        <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteUserGift({{ $uGift->id }})" wire:confirm="Удалить подарок из профиля юзера?">
                                            <x-lucide-trash-2 class="w-4 h-4" /> Убрать из профиля
                                        </x-ui.dropdown-menu-item>
                                    </x-ui.dropdown-menu-content>
                                </x-ui.dropdown-menu>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="7" class="py-12 text-center text-muted-foreground">
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
    <div x-show="$wire.showGiftModal" x-cloak @click.self="$wire.showGiftModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition @keydown.escape.window="$wire.showGiftModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">{{ $editingGiftId ? 'Редактировать подарок' : 'Добавить подарок' }}</h2>
                <button @click="$wire.showGiftModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex items-center gap-4">
                    <div class="flex-1 flex flex-col gap-2">
                        <x-ui.label for="g-name">Название</x-ui.label>
                        <x-ui.input id="g-name" wire:model.live="modalName" placeholder="Красная роза" />
                        @error('modalName') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-ui.label>Превью</x-ui.label>
                        <div class="w-16 h-16 bg-muted rounded-lg flex items-center justify-center overflow-hidden border border-border">
                            @if($modalImageUrl)
                                <img src="{{ $modalImageUrl }}" class="w-full h-full object-contain p-1">
                            @else
                                <x-lucide-image-off class="w-6 h-6 text-muted-foreground/50" />
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="g-slug">Slug (URL)</x-ui.label>
                    <x-ui.input id="g-slug" wire:model="modalSlug" placeholder="red_rose" />
                    @error('modalSlug') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="g-img">Image URL / Путь</x-ui.label>
                    <x-ui.input id="g-img" wire:model.live="modalImageUrl" placeholder="https://... или /uploads/gifts/rose.png" />
                    @error('modalImageUrl') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <x-ui.label for="g-price">Цена (Кредиты)</x-ui.label>
                        <x-ui.input id="g-price" wire:model="modalPrice" type="number" min="1" />
                        @error('modalPrice') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-ui.label>Категория</x-ui.label>
                        <x-ui.select wire:model="modalCategory">
                            <x-ui.select-trigger><x-ui.select-value /></x-ui.select-trigger>
                            <x-ui.select-content>
                                @foreach($this->categoriesList as $key => $cat)
                                    <x-ui.select-item value="{{ $key }}">{{ $cat }}</x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>
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
                <x-ui.button wire:click="{{ $editingGiftId ? 'updateGift' : 'saveGift' }}" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4 inline" /> Сохранить
                </x-ui.button>
            </div>
        </div>
    </div>
</div>