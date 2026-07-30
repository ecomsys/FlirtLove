<?php

use App\Actions\Admin\ToggleUserBanAction;
use App\Actions\Admin\UpdateUserLocationAction;
use App\Models\User;
use App\Models\BlockedIp;
use App\Models\ModerationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public User $user;
    public ?string $address = null;
    public ?float $editLat = null;
    public ?float $editLng = null;

    private ToggleUserBanAction $toggleUserBanAction;
    private UpdateUserLocationAction $updateLocationAction;

    public function boot(ToggleUserBanAction $toggleUserBanAction, UpdateUserLocationAction $updateLocationAction): void
    {
        $this->toggleUserBanAction = $toggleUserBanAction;
        $this->updateLocationAction = $updateLocationAction;
    }

    public function mount(User $user): void
    {
        $this->user = $this->loadUserWithRelations($user);
        
        $profile = $this->user->profile;
        
        if ($profile) {
            if ($profile->map_lat && $profile->map_lng) {
                $this->editLat = (float) $profile->map_lat;
                $this->editLng = (float) $profile->map_lng;
            }
            $this->address = !empty($profile->address) ? $profile->address : null;
        }
    }

    //  Восстанавливаем связи после каждого запроса
    public function hydrate(): void
    {
        // После гидратации модели User (когда Livewire восстанавливает её из запроса),
        // связи и счетчики теряются. Мы принудительно их перезагружаем.
        $this->user = $this->loadUserWithRelations($this->user, force: true);
    }

    protected function loadUserWithRelations(User $user, bool $force = false): User
    {
        if ($force) {
            $user->refresh(); // Сбрасываем кэш модели, чтобы получить свежие данные из БД
        }
        
        return $user->load([
            'profile' => fn($q) => $q->select('*')->selectRaw('ST_Y(location::geometry) as map_lat, ST_X(location::geometry) as map_lng'),
            'preferences',
            'albums' => function ($query) {
                $query->with(['photos' => function ($q) {
                    $q->reorder('is_primary', 'desc')
                      ->orderBy('position', 'asc')
                      ->orderBy('created_at', 'desc');
                }])->orderBy('is_default', 'desc')->orderBy('name');
            },
            'photos' => fn($q) => $q->where('status', 'approved')->orderBy('is_primary', 'desc')->limit(1),
            'receivedReports' => fn($q) => $q->where('status', 'pending')->latest()->with('user'),
            'sentReports' => fn($q) => $q->where('status', 'pending')->latest()->with('reportedUser'),
            'photoComments' => fn($q) => $q->where('status', 'pending')->with('photo'),
        ])
        ->loadCount([
            'photos',
            'photos as pending_photos_count' => fn($q) => $q->where('status', 'pending'),
            'photoComments as comments_count',
            'photoComments as pending_comments_count' => fn($q) => $q->where('status', 'pending'),
            'receivedReports as received_reports_count',
            'receivedReports as pending_received_reports_count' => fn($q) => $q->where('status', 'pending'),
            'sentReports as pending_sent_reports_count' => fn($q) => $q->where('status', 'pending'),
            'swipesGiven as swipes_given_count',
            'swipesReceived as swipes_received_count',
        ]);
    }

    protected function refreshUser(): void
    {
        $this->user = $this->loadUserWithRelations($this->user, force: true);
        unset($this->stats);
        unset($this->pendingPhotos);
    }

    #[Computed]
    public function stats(): array
    {
        $user = $this->user;
        return [
            'photos_count' => $user->photos_count ?? $user->photos()->count(),
            'pending_photos' => $user->pending_photos_count ?? $user->photos()->where('status', 'pending')->count(),
            'comments_count' => $user->comments_count ?? $user->photoComments()->count(),
            'pending_comments' => $user->pending_comments_count ?? $user->photoComments()->where('status', 'pending')->count(),
            'received_reports' => $user->received_reports_count ?? $user->receivedReports()->count(),
            'pending_received_reports' => $user->pending_received_reports_count ?? $user->receivedReports()->where('status', 'pending')->count(),
            'sent_reports' => $user->pending_sent_reports_count ?? $user->sentReports()->where('status', 'pending')->count(),
            'matches_count' => $user->matches()->count(),
            'swipes_given' => $user->swipes_given_count ?? $user->swipesGiven()->count(),
            'swipes_received' => $user->swipes_received_count ?? $user->swipesReceived()->count(),
        ];
    }

   public function toggleShadowban(): void
    {
        if ($this->user->id === auth()->id()) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя дать теневой бан самому себе!');
            return;
        }

        $this->user->update([
            'is_shadowbanned' => !$this->user->is_shadowbanned,
        ]);

        $status = $this->user->is_shadowbanned ? 'включен' : 'отключен';
      
        ModerationLog::create([
            'admin_id' => auth()->id(),
            'user_id' => $this->user->id,
            'action' => $this->user->is_shadowbanned ? 'shadowban_enabled' : 'shadowban_disabled',
        ]);
        
        $this->dispatch('show-toast', type: 'success', message: "Теневой бан {$status}");
        $this->refreshUser();
    }
    #[Computed]
    public function pendingPhotos()
    {
        return $this->user->photos()->where('status', 'pending')->get();
    }

    public function updateAddressFromCoords(float $lat, float $lng): ?string
    {
        $result = $this->updateLocationAction->execute($this->user, $lat, $lng, $this->address);
        $this->address = $result['address'] ?? null;
        
        $this->dispatch('address-updated', address: $this->address);
         
        return $this->address;         
    }

    public function updateLocation(): void
    {
        $this->validate([
            'editLat' => 'required|numeric|between:-90,90',
            'editLng' => 'required|numeric|between:-180,180',
        ]);

        $result = $this->updateLocationAction->execute(
            $this->user,
            (float) $this->editLat,
            (float) $this->editLng,
            $this->address
        );

        if ($result['success']) {
            $this->address = $result['address'];
            $this->refreshUser(); 
            $this->dispatch('show-toast', type: 'success', message: $result['message']);
        }
    }

   public function toggleBan(): void
    {
        if ($this->user->id === auth()->id()) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя забанить самого себя!');
            return;
        }

        $result = $this->toggleUserBanAction->execute($this->user, 'Нарушение правил через админ-панель');
        
        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);
        
        if ($result['success']) {         
            ModerationLog::create([
                'admin_id' => auth()->id(),
                'user_id' => $this->user->id,
                'action' => $this->user->is_banned ? 'user_unbanned' : 'user_banned',
                'reason' => 'Нарушение правил через админ-панель',
            ]);

            $this->refreshUser();
        }
    }

    public function deletePhoto(int $photoId): void
    {
        $photo = $this->user->photos()->find($photoId);
        
        if (!$photo) {
            $this->dispatch('show-toast', type: 'error', message: 'Фото не найдено');
            return;
        }

        DB::transaction(function () use ($photo) {
           
            ModerationLog::create([
                'admin_id' => auth()->id(),
                'user_id' => $this->user->id,
                'action' => 'photo_deleted',
                'subject_type' => 'Photo',
                'subject_id' => $photo->id,
                'metadata' => [
                    'path_original' => $photo->path_original, // Сохраняем путь, хоть файл и удалится
                    'status' => $photo->status,
                    'is_intimate' => $photo->is_intimate,
                ],
            ]);

            $photo->delete();
            $this->user->notify(new \App\Notifications\PhotoModerated($photo->id, $this->user->id, 'deleted', 1));
            
            Log::info('Фото пользователя удалено администратором', [
                'photo_id' => $photo->id,
                'user_id' => $this->user->id,
                'admin_id' => auth()->id(),
            ]);
        });

        $this->refreshUser(); 
        $this->dispatch('show-toast', type: 'success', message: 'Фото удалено');
    }

    public function setPrimaryPhoto(int $photoId): void
    {
        $photo = $this->user->photos()->find($photoId);
        
        if (!$photo) {
            $this->dispatch('show-toast', type: 'error', message: 'Фото не найдено');
            return;
        }

        if ($photo->status !== 'approved') {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя сделать неодобренное фото основным');
            return;
        }

        DB::transaction(function () use ($photoId) {
            $this->user->photos()->update(['is_primary' => false]);
            $this->user->photos()->where('id', $photoId)->update(['is_primary' => true]);
            
            Log::info('Основное фото пользователя изменено', [
                'photo_id' => $photoId,
                'user_id' => $this->user->id,
                'admin_id' => auth()->id(),
            ]);
        });

        $this->refreshUser(); 
        $this->dispatch('show-toast', type: 'success', message: 'Фото установлено как основное');
    }

    public function searchByIp(string $ip): void
    {
        if (!$ip) return;

        // Ищем всех юзеров с этим IP, кроме текущего
        $users = User::where('last_login_ip', $ip)
            ->where('id', '!=', $this->user->id)
            ->select('id', 'name', 'last_login_ip')
            ->limit(20)
            ->get();

        // Передаем результаты во флеш-сессию, чтобы отобразить в UI
        session()->flash('ip_search_results', $users);
        session()->flash('ip_search_results_ip', $ip);

        $this->dispatch('show-toast', type: 'info', message: "Найдено аккаунтов: " . $users->count());
    }

    public function blockIp(string $ip): void
    {
        if (!$ip) return;

        BlockedIp::create([
            'ip_address' => $ip,
            'reason' => 'Блокировка из админки (пользователь: ' . $this->user->id . ')',
            'blocked_by' => auth()->id(),
        ]);

        $this->dispatch('show-toast', type: 'success', message: "IP {$ip} заблокирован");
    }

    public function unblockIp(string $ip): void
    {
        BlockedIp::where('ip_address', $ip)->delete();
        $this->dispatch('show-toast', type: 'success', message: "IP {$ip} разблокирован");
    }
}; 
?>

<!-- Инициализируем Alpine (Твоя оригинальная структура) -->
<div 
    x-data="{
        tab: localStorage.getItem('admin_user_tab') || 'profile',
        initMapTab() {
            if (this.tab === 'map') {
                this.$nextTick(() => {
                    window.setupMap(
                        @js($editLat),
                        @js($editLng),
                        @js($address),
                        @js([
                            'id' => $user->id,
                            'name' => $user->name,
                            'avatar_url' => $user->avatar_url,
                            'birth_date' => $user->profile?->birth_date?->format('Y-m-d'),
                        ]),
                        $wire
                    );
                });
            }
        },
        init() {
            this.$watch('tab', (value) => {
                localStorage.setItem('admin_user_tab', value);
                this.initMapTab();
            });
            this.initMapTab();

            this.$wire.on('address-updated', (address) => {
                const addressElement = document.getElementById('user-address');
                if (addressElement) addressElement.innerText = address || 'Не определён';
                
                if (window.leafletMarker && window.leafletUserData) {
                    const popup = window.leafletMarker.getPopup();
                    if (popup) popup.setContent(window.createPopupContent(window.leafletUserData, address));
                }
            });
        }
    }" 
    class="space-y-6"
>

@php
    $profile = $user->profile;
    $preferences = $user->preferences;

    $getOption = function($type, $value) {
        if ($value === null || $value === 0 || $value === '0') return null;
        return config("profile_options.{$type}.{$value}");
    };

    $getOptions = function($type, $values) {
        if (empty($values) || !is_array($values)) return [];
        $result = [];
        foreach ($values as $val) {
            $text = config("profile_options.{$type}.{$val}");
            if ($text) $result[] = $text;
        }
        return $result;
    };
@endphp

    <!-- Шапка профиля -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.users.index') }}" class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2 flex-wrap">
                    {{ $user->name }}
                    <span class="text-xs text-muted-foreground font-normal">(ID: {{ $user->id }})</span>
                    @if ($user->is_admin) <x-ui.badge variant="default" size="sm">Admin</x-ui.badge> @endif
                    @if ($user->is_banned) <x-ui.badge variant="destructive" size="sm">Забанен</x-ui.badge> @endif
                </h1>
                <p class="text-sm text-muted-foreground">{{ $user->email }}</p>
            </div>
        </div>

       <div class="flex items-center gap-2">
            @if (!$user->is_admin)
                <x-ui.button wire:click="toggleBan" wire:loading.attr="disabled" wire:confirm="Изменить статус блокировки пользователя?" variant="{{ $user->is_banned ? 'success' : 'destructive' }}">
                    <span wire:loading.remove wire:target="toggleBan">{{ $user->is_banned ? 'Разбанить' : 'Забанить' }}</span>
                    <span wire:loading wire:target="toggleBan">Обработка...</span>
                </x-ui.button>

                <!--  КНОПКА ТЕНЕВОГО БАНА -->
                <x-ui.button wire:click="toggleShadowban" wire:loading.attr="disabled" wire:confirm="Включить/выключить теневой бан?" variant="{{ $user->is_shadowbanned ? 'success' : 'warning' }}">
                    <span wire:loading.remove wire:target="toggleShadowban">{{ $user->is_shadowbanned ? 'Снять теневой бан' : 'Теневой бан' }}</span>
                    <span wire:loading wire:target="toggleShadowban">Обработка...</span>
                </x-ui.button>
            @endif

            <a href="{{ route('admin.support.show', ['user_id' => $user->id]) }}" wire:navigate>
                <x-ui.button variant="default">
                    <x-lucide-headphones class="w-4 h-4" /> Чат поддержки
                </x-ui.button>
            </a>

            <x-ui.button variant="outline" onclick="window.location.href='mailto:{{ $user->email }}'">
                <x-lucide-mail class="w-4 h-4" /> Email
            </x-ui.button>           
        </div>
    </div>

    <!-- Меню вкладок -->
    <div class="border-b border-border">
        <nav class="flex gap-4 flex-wrap">
            <button @click="tab = 'profile'" :class="tab === 'profile' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-user class="w-4 h-4 inline mr-1" /> Профиль
            </button>
            <button @click="tab = 'photos'" :class="tab === 'photos' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-image class="w-4 h-4 inline mr-1" /> Фотографии ({{ $user->photos_count }})
            </button>
            <button @click="tab = 'sessions'" :class="tab === 'sessions' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-clock class="w-4 h-4 inline mr-1" /> Активность
            </button>
            <button @click="tab = 'moderation'" :class="tab === 'moderation' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-shield class="w-4 h-4 inline mr-1" /> Модерация
                @if($user->pending_photos_count > 0 || $user->pending_comments_count > 0 || $user->pending_received_reports_count > 0)
                    <span class="ml-1 w-2 h-2 rounded-full bg-destructive inline-block"></span>
                @endif
            </button>
            <button @click="tab = 'map'" :class="tab === 'map' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-map class="w-4 h-4 inline mr-1" /> Адрес
            </button>
        </nav>
    </div>

    <!-- Контент вкладок -->
    <div class="bg-card border border-border rounded-lg p-6">

               <!-- Вкладка 1: ПРОФИЛЬ -->
        <div x-show="tab === 'profile'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- ЛЕВАЯ КОЛОНКА -->
                <div class="space-y-4">
                    <!-- Карточка юзера -->
                    <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                        <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="xl" userId="{{ $user->id }}" showStatus="true" />
                        <div>
                            <p class="font-medium">{{ $user->name }}</p>
                            <p class="text-xs text-muted-foreground">{{ $user->email }}</p>
                            <p class="text-xs text-muted-foreground">ID: {{ $user->id }}</p>
                            <div class="flex gap-1 mt-2 flex-wrap">
                                @if ($user->has_active_premium) <x-ui.badge variant="warning" size="xs"><x-lucide-crown class="w-3 h-3 inline mr-1" />Premium</x-ui.badge> @endif
                                @if ($user->is_verified) <x-ui.badge variant="info" size="xs">Верифицирован</x-ui.badge> @endif
                            </div>
                        </div>
                    </div>

                    <!-- Основная информация (Объединили) -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Основная информация</p>
                        <div class="divide-y divide-border/50">
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Пол</span>
                                <span class="text-sm font-medium">
                                    @if ($profile?->gender === 'male') Мужской
                                    @elseif($profile?->gender === 'female') Женский
                                    @else Не указан @endif
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Дата рождения</span>
                                <span class="text-sm font-medium">
                                    {{ $profile?->birth_date ? $profile->birth_date->format('d.m.Y') : 'Не указана' }}
                                    @if ($profile?->birth_date) <span class="text-xs text-muted-foreground">({{ $profile->birth_date->age }} лет)</span> @endif
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Город / Страна</span>
                                <span class="text-sm font-medium text-right">
                                    {{ $profile?->city ?? 'Не указан' }} {{ $profile?->country ? ', ' . $profile->country : '' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Цель знакомства</span>
                                <span class="text-sm font-medium">
                                    @switch($profile?->dating_goal)
                                        @case('friends') Поиск друзей @break
                                        @case('romantic') Романтика @break
                                        @case('family') Создание семьи @break
                                        @case('casual') Свободные отношения @break
                                        @default Не указана
                                    @endswitch
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- О себе -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-1">О себе</p>
                        <p class="text-sm text-muted-foreground">{{ $profile?->bio ?? 'Пользователь не заполнил информацию о себе' }}</p>
                        @if($profile?->looking_for)
                            <div class="mt-2 pt-2 border-t border-border/50">
                                <p class="text-xs text-muted-foreground uppercase mb-1">Кого ищет</p>
                                <p class="text-sm text-muted-foreground">{{ $profile->looking_for }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- Интересы -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Интересы</p>
                        <div class="flex flex-wrap gap-1">
                            @if ($profile?->interests && is_array($profile->interests) && count($profile->interests) > 0)
                                @foreach ($profile->interests as $interest)
                                    <x-ui.badge variant="secondary" size="xs" wire:key="interest-{{ $loop->index }}">{{ $interest }}</x-ui.badge>
                                @endforeach
                            @else
                                <span class="text-sm text-muted-foreground">Не указаны</span>
                            @endif
                        </div>
                    </div>

                    <!-- Внешность -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Внешность</p>
                        <div class="divide-y divide-border/50">
                            @if($profile?->height) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Рост</span><span class="text-sm font-medium">{{ $profile->height }} см</span></div> @endif
                            @if($profile?->weight) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Вес</span><span class="text-sm font-medium">{{ $profile->weight }} кг</span></div> @endif
                            @if($getOption('body_type', $profile?->body_type)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Телосложение</span><span class="text-sm font-medium">{{ $getOption('body_type', $profile->body_type) }}</span></div> @endif
                            @if($getOption('eye_color', $profile?->eye_color)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Цвет глаз</span><span class="text-sm font-medium">{{ $getOption('eye_color', $profile->eye_color) }}</span></div> @endif
                            @if($getOption('hair_color', $profile?->hair_color)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Цвет волос</span><span class="text-sm font-medium">{{ $getOption('hair_color', $profile->hair_color) }}</span></div> @endif
                            @if($profile?->zodiac_sign) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Знак зодиака</span><span class="text-sm font-medium">{{ $profile->zodiac_sign }}</span></div> @endif
                        </div>
                    </div>

                    <!-- Личная жизнь и Быт -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Личная жизнь и Быт</p>
                        <div class="divide-y divide-border/50">
                            @if($getOption('relationship_status', $profile?->relationship_status)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Отношения</span><span class="text-sm font-medium">{{ $getOption('relationship_status', $profile->relationship_status) }}</span></div> @endif
                            @if($getOption('children_status', $profile?->children_status)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Дети</span><span class="text-sm font-medium">{{ $getOption('children_status', $profile->children_status) }}</span></div> @endif
                            @if($getOption('pets', $profile?->pets)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Животные</span><span class="text-sm font-medium">{{ $getOption('pets', $profile->pets) }}</span></div> @endif
                            @if($getOption('housing', $profile?->housing)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Жилье</span><span class="text-sm font-medium">{{ $getOption('housing', $profile->housing) }}</span></div> @endif
                            @if($getOption('has_car', $profile?->has_car)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Автомобиль</span><span class="text-sm font-medium">{{ $getOption('has_car', $profile->has_car) }}</span></div> @endif
                        </div>
                    </div>

                    <!-- Работа и Образование -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Работа и Образование</p>
                        <div class="divide-y divide-border/50">
                            @if($getOption('education_level', $profile?->education)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Образование</span><span class="text-sm font-medium">{{ $getOption('education_level', $profile->education) }}</span></div> @endif
                            @if($profile?->institution) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Учебное заведение</span><span class="text-sm font-medium text-right">{{ $profile->institution }}</span></div> @endif
                            @if($profile?->institution_year) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Год выпуска</span><span class="text-sm font-medium">{{ $profile->institution_year }}</span></div> @endif
                            @if($profile?->activity) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Сфера</span><span class="text-sm font-medium">{{ $profile->activity }}</span></div> @endif
                            @if($profile?->position) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Должность</span><span class="text-sm font-medium">{{ $profile->position }}</span></div> @endif
                        </div>
                    </div>

                    <!-- Привычки и Увлечения -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Привычки и Увлечения</p>
                        <div class="divide-y divide-border/50">
                            @if($getOption('smoking', $profile?->smoking)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Курение</span><span class="text-sm font-medium">{{ $getOption('smoking', $profile->smoking) }}</span></div> @endif
                            @if($getOption('alcohol', $profile?->alcohol)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Алкоголь</span><span class="text-sm font-medium">{{ $getOption('alcohol', $profile->alcohol) }}</span></div> @endif
                            @php $langs = $getOptions('languages', $profile?->languages ?? []); @endphp
                            @if(!empty($langs))
                                <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Языки</span><span class="text-sm font-medium text-right">{{ implode(', ', $langs) }}</span></div>
                            @endif
                            @php $spts = $getOptions('sports', $profile?->sports ?? []); @endphp
                            @if(!empty($spts))
                                <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Спорт</span><span class="text-sm font-medium text-right">{{ implode(', ', $spts) }}</span></div>
                            @endif
                        </div>
                    </div>

                    <!-- Предпочтения в поиске (С РАСШИРЕННЫМИ ФИЛЬТРАМИ) -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Предпочтения в поиске</p>
                        @php $sf = $preferences?->search_filters; @endphp
                        <div class="divide-y divide-border/50">
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Ищет</span>
                                <span class="text-sm font-medium">
                                    @if($preferences?->preferred_gender == 'any') Всех
                                    @elseif($preferences?->preferred_gender == 'male') Мужчин
                                    @elseif($preferences?->preferred_gender == 'female') Женщин
                                    @else Не указано @endif
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Возраст</span>
                                <span class="text-sm font-medium">{{ $preferences?->preferred_age_min ?? 18 }} - {{ $preferences?->preferred_age_max ?? 99 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Радиус</span>
                                <span class="text-sm font-medium">{{ $preferences?->preferred_distance_km ?? 50 }} км</span>
                            </div>
                            
                            <!-- Расширенные фильтры (из search_filters) -->
                            @if($getOption('body_type', $sf['body_type'] ?? null)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Телосложение</span><span class="text-sm font-medium">{{ $getOption('body_type', $sf['body_type']) }}</span></div> @endif
                            @if($getOption('eye_color', $sf['eye_color'] ?? null)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Цвет глаз</span><span class="text-sm font-medium">{{ $getOption('eye_color', $sf['eye_color']) }}</span></div> @endif
                            @if($getOption('hair_color', $sf['hair_color'] ?? null)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Цвет волос</span><span class="text-sm font-medium">{{ $getOption('hair_color', $sf['hair_color']) }}</span></div> @endif
                            @if(!empty($sf['height_from']) || !empty($sf['height_to'])) 
                                <div class="flex justify-between items-center py-1.5">
                                    <span class="text-xs text-muted-foreground">Рост</span>
                                    <span class="text-sm font-medium">{{ $sf['height_from'] ?? '∞' }} - {{ $sf['height_to'] ?? '∞' }} см</span>
                                </div> 
                            @endif
                            @if($getOption('education_level', $sf['education'] ?? null)) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Образование</span><span class="text-sm font-medium">{{ $getOption('education_level', $sf['education']) }}</span></div> @endif
                            @if(!empty($sf['zodiac_sign'])) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Знак зодиака</span><span class="text-sm font-medium">{{ $sf['zodiac_sign'] }}</span></div> @endif
                            @if(!empty($sf['is_verified_only'])) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Верификация</span><span class="text-sm font-medium text-primary">Только верифицированные</span></div> @endif
                            @if(!empty($sf['is_premium_only'])) <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Подписка</span><span class="text-sm font-medium text-yellow-500">Только Premium</span></div> @endif
                        </div>
                    </div>
                </div>

                <!-- ПРАВАЯ КОЛОНКА -->
                <div class="space-y-4">
                    <!-- Метрики (Объединили) -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Активность и Метрики</p>
                        <div class="grid grid-cols-2 gap-x-8 divide-y divide-border/50">
                            <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Просмотры</span><span class="text-sm font-medium">{{ $profile?->profile_views ?? 0 }}</span></div>
                            <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Лайки</span><span class="text-sm font-medium">{{ $profile?->likes_count ?? 0 }}</span></div>
                            <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Фото</span><span class="text-sm font-medium">{{ $user->photos_count }}</span></div>
                            <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Суперлайки</span><span class="text-sm font-medium">{{ $user->superlikes_remaining }}</span></div>
                            <div class="flex justify-between items-center py-1.5 col-span-2"><span class="text-xs text-muted-foreground">Регистрация</span><span class="text-sm font-medium">{{ $user->created_at->format('d.m.Y') }}</span></div>
                            <div class="flex justify-between items-center py-1.5 col-span-2">
                                <span class="text-xs text-muted-foreground">Последний визит</span>
                                <span class="text-sm font-medium">
                                    {{ $user->last_seen ? $user->last_seen->diffForHumans() : 'Никогда' }}
                                    @if($user->is_online) <span class="text-xs text-green-500 ml-1">● Онлайн</span> @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Статусы аккаунта (Объединили) -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Статусы</p>
                        <div class="grid grid-cols-2 gap-8">
                            
                            <div>
                                <p class="text-xs text-muted-foreground mb-1">Подписка</p>
                                @if ($user->has_active_premium)
                                    <x-ui.badge variant="warning" size="xs"><x-lucide-crown class="w-3 h-3 inline mr-1" />Premium</x-ui.badge>
                                    <span class="text-[10px] text-muted-foreground block mt-0.5">{{ $user->premium_expires_at ? 'до ' . $user->premium_expires_at->format('d.m.Y') : 'Бессрочно' }}</span>
                                @else
                                    <x-ui.badge variant="secondary" size="xs">Бесплатный</x-ui.badge>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground mb-1">Email</p>
                                @if ($user->email_verified_at) <x-ui.badge variant="success" size="xs">Подтвержден</x-ui.badge>
                                @else <x-ui.badge variant="destructive" size="xs">Не подтвержден</x-ui.badge> @endif
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground mb-1">Онбординг</p>
                                @if ($user->has_completed_onboarding) <x-ui.badge variant="success" size="xs">Завершен</x-ui.badge>
                                @else <x-ui.badge variant="warning" size="xs">Не завершен</x-ui.badge> @endif
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground mb-1">Аккаунт</p>
                                @if ($user->is_banned) <x-ui.badge variant="destructive" size="xs">Забанен</x-ui.badge>
                                @elseif ($user->is_shadowbanned) <x-ui.badge variant="warning" size="xs">Теневой бан</x-ui.badge> <!-- ДОБАВИЛСЯ -->
                                @elseif ($user->is_deactivated) <x-ui.badge variant="warning" size="xs">Заморожен</x-ui.badge>
                                @else <x-ui.badge variant="success" size="xs">Активен</x-ui.badge> @endif
                            </div>
                        </div>
                    </div>

                    <!-- Настройки чата -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Настройки чата</p>
                        <div class="divide-y divide-border/50">
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Фильтр сообщений</span>
                                @if ($preferences?->chat_filter_enabled) <x-ui.badge variant="info" size="xs">Включен</x-ui.badge>
                                @else <span class="text-sm font-medium text-muted-foreground">Доступен всем</span> @endif
                            </div>
                            @if ($preferences?->chat_filter_enabled && $preferences?->chat_filter_settings)
                                @php $cf = $preferences->chat_filter_settings; @endphp
                                <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Принимает от</span><span class="text-sm font-medium">{{ $cf['gender'] ?? 'any' }}</span></div>
                                <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Возраст</span><span class="text-sm font-medium">{{ $cf['age_from'] ?? 18 }} - {{ $cf['age_to'] ?? 99 }}</span></div>
                                @if(!empty($cf['is_verified_only'])) <div class="py-1.5 text-xs text-primary font-medium">Только верифицированные</div> @endif
                                @if(!empty($cf['is_premium_only'])) <div class="py-1.5 text-xs text-yellow-500 font-medium">Только Premium</div> @endif
                            @endif
                        </div>
                    </div>

                    <!-- Приватность -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Приватность</p>
                        <div class="divide-y divide-border/50">
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Режим "Невидимка"</span>
                                @if($preferences?->is_invisible) <span class="text-sm font-medium text-green-500">Вкл</span> @else <span class="text-sm font-medium text-muted-foreground">Выкл</span> @endif
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Скрывать 18+ фото</span>
                                @if($preferences?->hide_intimate) <span class="text-sm font-medium text-green-500">Да</span> @else <span class="text-sm font-medium text-muted-foreground">Нет</span> @endif
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Комментарии к фото</span>
                                @if($preferences?->disable_photo_comments) <span class="text-sm font-medium text-destructive">Запрещены</span> @else <span class="text-sm font-medium text-muted-foreground">Разрешены</span> @endif
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Скрыт из поиска</span>
                                @if($preferences?->hide_from_search) <span class="text-sm font-medium text-yellow-500">Да</span> @else <span class="text-sm font-medium text-muted-foreground">Нет</span> @endif
                            </div>
                        </div>
                    </div>

                                       <!-- Уведомления -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Уведомления</p>
                        @php 
                            $emailSettings = $preferences?->email_settings ?? [];
                            $pushEnabled = $preferences?->push_enabled ?? true;
                        @endphp
                        <div class="divide-y divide-border/50">
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Push (браузер)</span>
                                @if($pushEnabled) <span class="text-sm font-medium text-green-500">Вкл</span> @else <span class="text-sm font-medium text-muted-foreground">Выкл</span> @endif
                            </div>
                            <hr>
                            <div class="py-1.5 text-xs text-muted-foreground uppercase mt-1">Уведомления на Email:</div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Сообщения</span> 
                                @if($emailSettings['on_message'] ?? true) <x-lucide-check class="w-4 h-4 text-green-500" /> @else <x-lucide-x class="w-4 h-4 text-muted-foreground" /> @endif
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Лайки</span> 
                                @if($emailSettings['on_like'] ?? true) <x-lucide-check class="w-4 h-4 text-green-500" /> @else <x-lucide-x class="w-4 h-4 text-muted-foreground" /> @endif
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Просмотры</span> 
                                @if($emailSettings['on_view'] ?? false) <x-lucide-check class="w-4 h-4 text-green-500" /> @else <x-lucide-x class="w-4 h-4 text-muted-foreground" /> @endif
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Модерация</span> 
                                @if($emailSettings['on_photo_moderated'] ?? true) <x-lucide-check class="w-4 h-4 text-green-500" /> @else <x-lucide-x class="w-4 h-4 text-muted-foreground" /> @endif
                            </div>
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-xs text-muted-foreground">Рассылки</span> 
                                @if($emailSettings['on_broadcast'] ?? true) <x-lucide-check class="w-4 h-4 text-green-500" /> @else <x-lucide-x class="w-4 h-4 text-muted-foreground" /> @endif
                            </div>
                        </div>
                    </div>

                    <!-- Системные данные -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Системные данные</p>
                        <div class="divide-y divide-border/50">
                            <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Локаль</span><span class="text-sm font-mono font-medium">{{ $preferences?->locale ?? 'ru' }}</span></div>
                            <div class="flex justify-between items-center py-1.5"><span class="text-xs text-muted-foreground">Тема</span><span class="text-sm font-mono font-medium">{{ $preferences?->theme ?? 'light' }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Вкладка 2: ФОТОГРАФИИ -->
        <div x-show="tab === 'photos'" style="display: none;">
            <div>
                @if ($user->albums->isNotEmpty())
                    @foreach ($user->albums as $album)
                        <div class="mb-8 last:mb-0" wire:key="album-{{ $album->id }}">
                            <h3 class="text-lg font-medium mb-3 flex items-center gap-2">
                                <x-lucide-folder class="w-5 h-5 text-muted-foreground" />
                                {{ $album->name }}
                                @if ($album->is_default) <x-ui.badge variant="secondary" size="xs">Основной</x-ui.badge> @endif
                                <span class="text-sm text-muted-foreground font-normal">({{ $album->photos->count() }} фото)</span>
                            </h3>
                            @if ($album->photos->isNotEmpty())
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                    @foreach ($album->photos as $photo)
                                        <div wire:key="photo-{{ $photo->id }}" class="relative group border border-border rounded-lg overflow-hidden bg-muted">
                                            <a href="{{ $photo->large_url ?? $photo->url }}" data-fancybox="user-gallery" data-caption="{{ $user->name }} - {{ $album->name }}">
                                                <img src="{{ $photo->thumb_url ?? $photo->url }}" class="w-full aspect-square object-cover group-hover:scale-105 transition-transform duration-300" alt="User Photo">
                                            </a>
                                            <div class="absolute top-2 left-2 flex flex-col gap-1">
                                                @if ($photo->is_primary) <x-ui.badge size="xs">Аватар</x-ui.badge> @endif
                                                @if ($photo->is_intimate) <x-ui.badge variant="destructive" size="xs">18+</x-ui.badge> @endif
                                            </div>
                                            <div class="absolute top-2 right-2">
                                                @if ($photo->status == 'approved') <x-ui.badge variant="success" size="xs">Одобрено</x-ui.badge>
                                                @elseif($photo->status == 'pending') <x-ui.badge variant="warning" size="xs">Ожидает</x-ui.badge>
                                                @else <x-ui.badge variant="destructive" size="xs">Отклонено</x-ui.badge> @endif
                                            </div>
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                                                <x-lucide-maximize-2 class="w-8 h-8 text-white drop-shadow-lg" />
                                            </div>
                                            <div class="absolute right-4 bottom-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <div class="flex gap-2 pointer-events-auto">
                                                    @if (!$photo->is_primary && $photo->status === 'approved')
                                                        <x-ui.button wire:click="setPrimaryPhoto({{ $photo->id }})" wire:confirm="Сделать это фото основным?" variant="default" size="icon-sm" class="text-white hover:bg-white/20" title="Сделать основным">
                                                            <x-lucide-star class="w-5 h-5" />
                                                        </x-ui.button>
                                                    @endif
                                                    <x-ui.button wire:click="deletePhoto({{ $photo->id }})" wire:confirm="Удалить это фото навсегда?" variant="destructive" size="icon-sm" class="text-white hover:bg-destructive/60" title="Удалить">
                                                        <x-lucide-trash-2 class="w-5 h-5" />
                                                    </x-ui.button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-muted-foreground">В этом альбоме пока нет фотографий</p>
                            @endif
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-12 text-muted-foreground">
                        <x-lucide-image class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p>У пользователя нет альбомов</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Вкладка 3: АКТИВНОСТЬ -->
        <div x-show="tab === 'sessions'" style="display: none;" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary"><x-lucide-clock class="w-5 h-5" /></div>
                    <div>
                        <p class="text-xs text-muted-foreground">Последний вход</p>
                        <p class="text-sm font-medium mt-1">{{ $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : 'Никогда' }}</p>
                        @if ($user->last_login_at) <p class="text-xs text-muted-foreground">{{ $user->last_login_at->diffForHumans() }}</p> @endif
                    </div>
                </div>
                <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary"><x-lucide-wifi class="w-5 h-5" /></div>
                    <div class="flex-1">
                        <p class="text-xs text-muted-foreground">IP-адрес</p>
                        <p class="text-sm font-medium mt-1 font-mono">{{ $user->last_login_ip ?? 'Нет данных' }}</p>
                    </div>
                    @if($user->last_login_ip)
                        <div class="flex gap-2">
                            <x-ui.button wire:click="searchByIp('{{ $user->last_login_ip }}')" variant="outline" size="sm">
                                <x-lucide-search class="w-4 h-4" /> Найти еще аккаунты 
                            </x-ui.button>
                            
                            @php $isBlockedIp = \App\Models\BlockedIp::where('ip_address', $user->last_login_ip)->exists(); @endphp
                            @if($isBlockedIp)
                                <x-ui.button wire:click="unblockIp('{{ $user->last_login_ip }}')" variant="success" size="sm">
                                    <x-lucide-unlock class="w-4 h-4" /> Разблокировать IP
                                </x-ui.button>
                            @else
                                <x-ui.button wire:click="blockIp('{{ $user->last_login_ip }}')" variant="destructive" size="sm" wire:confirm="Заблокировать этот IP для всех?">
                                    <x-lucide-ban class="w-4 h-4" /> Заблокировать IP
                                </x-ui.button>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Блок с результатами поиска мультиакков -->
                @if(session('ip_search_results'))
                    <div class="p-4 bg-muted/20 rounded-lg border border-border col-span-2">
                        <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Найдены аккаунты с IP: {{ session('ip_search_results_ip') }}</p>
                        <div class="space-y-2">
                            @foreach(session('ip_search_results') as $foundUser)
                                <div class="flex items-center justify-between bg-card p-2 rounded border border-border">
                                    <div>
                                        <span class="text-sm font-medium">{{ $foundUser->name }}</span>
                                        <span class="text-xs text-muted-foreground ml-2">(ID: {{ $foundUser->id }})</span>
                                    </div>
                                    <a href="{{ route('admin.users.show', $foundUser->id) }}" class="text-xs text-primary hover:underline">Открыть</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary"><x-lucide-calendar class="w-5 h-5" /></div>
                    <div>
                        <p class="text-xs text-muted-foreground">Дата регистрации</p>
                        <p class="text-sm font-medium mt-1">{{ $user->created_at->format('d.m.Y H:i') }}</p>
                        <p class="text-xs text-muted-foreground">{{ $user->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary"><x-lucide-edit class="w-5 h-5" /></div>
                    <div>
                        <p class="text-xs text-muted-foreground">Последнее обновление</p>
                        <p class="text-sm font-medium mt-1">{{ $user->updated_at->format('d.m.Y H:i') }}</p>
                        <p class="text-xs text-muted-foreground">{{ $user->updated_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Вкладка 4: МОДЕРАЦИЯ -->
        <div x-show="tab === 'moderation'" style="display: none;" class="space-y-6">
            
            <div>
                <h3 class="text-lg font-medium mb-3 flex items-center gap-2">
                    <x-lucide-image class="w-5 h-5 text-muted-foreground" />
                    Фото ожидают модерации 
                    <x-ui.badge variant="warning" size="xs">{{ $user->pending_photos_count }}</x-ui.badge>
                </h3>
                @if ($this->pendingPhotos->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        @foreach ($this->pendingPhotos as $photo)
                            <div wire:key="pend-photo-{{ $photo->id }}" class="relative group border border-border rounded-lg overflow-hidden bg-muted">
                                <img src="{{ $photo->thumb_url ?? $photo->url }}" class="w-full aspect-square object-cover" alt="Photo">
                                <div class="absolute inset-0 bg-black/20 flex items-center justify-center pointer-events-none">
                                     <x-ui.badge variant="warning" size="xs">Ожидает</x-ui.badge>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-muted-foreground">Нет фото, ожидающих модерации.</p>
                @endif
            </div>

            <div>
                <h3 class="text-lg font-medium mb-3 flex items-center gap-2">
                    <x-lucide-message-circle class="w-5 h-5 text-muted-foreground" />
                    Комментарии ожидают модерации 
                    <x-ui.badge variant="warning" size="xs">{{ $user->pending_comments_count }}</x-ui.badge>
                </h3>
                @if ($user->photoComments->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($user->photoComments as $comment)
                            <div class="p-3 bg-muted/10 border border-border rounded-lg flex items-center justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-foreground">{{ $comment->content }}</p>
                                    @if ($comment->photo)
                                        <a href="{{ $comment->photo->thumb_url ?? $comment->photo->url }}" data-fancybox="moderation-comments" class="text-xs text-primary hover:underline mt-1 inline-block">
                                            К фото #{{ $comment->photo_id }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-muted-foreground">Нет комментариев, ожидающих модерации.</p>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-muted/30 rounded-lg border border-border">
                    <h4 class="text-sm font-medium mb-2 flex items-center gap-2">
                        <x-lucide-alert-triangle class="w-4 h-4 text-destructive" />
                        Жалобы на пользователя 
                        <x-ui.badge variant="destructive" size="xs">{{ $user->pending_received_reports_count }}</x-ui.badge>
                    </h4>
                    @if ($user->receivedReports->isNotEmpty())
                        <div class="space-y-2 mt-2">
                            @foreach ($user->receivedReports as $report)
                                <div class="text-xs bg-card p-2 rounded border border-border">
                                    <p class="text-muted-foreground">От: {{ $report->user?->name ?? 'Удален' }}</p>
                                    <p class="text-foreground">{{ $report->reason }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-muted-foreground mt-2">Жалоб нет.</p>
                    @endif
                </div>

                <div class="p-4 bg-muted/30 rounded-lg border border-border">
                    <h4 class="text-sm font-medium mb-2 flex items-center gap-2">
                        <x-lucide-flag class="w-4 h-4 text-muted-foreground" />
                        Жалобы от пользователя 
                        <x-ui.badge variant="secondary" size="xs">{{ $user->pending_sent_reports_count }}</x-ui.badge>
                    </h4>
                    @if ($user->sentReports->isNotEmpty())
                        <div class="space-y-2 mt-2">
                            @foreach ($user->sentReports as $report)
                                <div class="text-xs bg-card p-2 rounded border border-border">
                                    <p class="text-muted-foreground">На: {{ $report->reportedUser?->name ?? 'Удален' }}</p>
                                    <p class="text-foreground">{{ $report->reason }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-muted-foreground mt-2">Жалоб нет.</p>
                    @endif
                </div>
            </div>
            
            <div class="p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                <p class="text-sm text-yellow-700 dark:text-yellow-400 flex items-center gap-2">
                    <x-lucide-alert-triangle class="w-5 h-5" />
                    <span>Если пользователь нарушает правила, вы можете заблокировать его через кнопку "Забанить" в шапке профиля</span>
                </p>
            </div>
        </div>

        <!-- Вкладка 5: КАРТА -->
        <div x-show="tab === 'map'" style="display: none;" class="space-y-4">   
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2" wire:ignore>
                    <div id="user-map" style="height: 450px; width: 100%; border-radius: 0.5rem; overflow: hidden;"></div>
                </div>
                <div class="space-y-4">
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-sm font-medium">📍 Адрес</p>
                        <p class="text-sm mt-1" id="user-address">{{ $address ?? 'Не определён' }}</p>
                    </div>
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-sm font-medium">Координаты</p>
                        <div class="mt-2 space-y-2">
                            <x-ui.input wire:model="editLat" id="map-lat-input" label="Широта" type="number" step="any" oninput="updateMarkerFromInputs()" />
                            <x-ui.input wire:model="editLng" id="map-lng-input" label="Долгота" type="number" step="any" oninput="updateMarkerFromInputs()" />
                            
                            <x-ui.button type="button" onclick="window.fetchAddress()" variant="outline" class="w-full">
                                <x-lucide-search class="w-4 h-4 inline" />
                                Определить адрес по координатам
                            </x-ui.button>

                            <x-ui.button wire:click="updateLocation" wire:loading.attr="disabled" class="w-full">
                                <span wire:loading.remove wire:target="updateLocation">Сохранить</span>
                                <span wire:loading wire:target="updateLocation" class="flex items-center justify-center gap-3">
                                    <x-ui.spinner class="w-4 h-4 inline" /> Сохранение...
                                </span>
                            </x-ui.button>
                        </div>
                    </div>
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground">🔁 Перетащите маркер или введите координаты. Нажмите "Определить адрес" для проверки, и "Сохранить" для записи в БД.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    if (typeof Fancybox !== 'undefined') {
        Fancybox.defaults.Hash = false;
    }

    window.leafletMap = null;
    window.leafletMarker = null;
    window.leafletUserData = null;
    window.livewireWire = null;

    window.createPopupContent = function(u, addr) {
        const avatar = u.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name) + '&background=random&size=50';
        const age = u.birth_date ? new Date().getFullYear() - new Date(u.birth_date).getFullYear() : '?';
        return `
            <div class="min-w-[12rem] font-sans select-none">
                <div class="flex items-center gap-3 mb-1.5">
                    <img src="${avatar}" class="w-10 h-10 rounded-full object-cover" alt="Avatar">
                    <div>
                        <strong class="text-base">${u.name}</strong><br>
                        <span class="text-sm text-muted-foreground">Возраст: ${age}</span>
                    </div>
                </div>
                <div class="text-sm text-muted-foreground border-t border-border pt-1.5">
                    ${addr || 'Адрес не определён'}
                </div>
            </div>
        `;
    };

    window.setupMap = function(initialLat, initialLng, initialAddress, user, wire) {
        const container = document.getElementById('user-map');
        if (!container) return;

        window.livewireWire = wire;

        if (window.leafletMap) {
            setTimeout(() => {
                window.leafletMap.invalidateSize();
                if (window.leafletMarker) window.leafletMarker.openPopup();
            }, 50);
            return;
        }

        window.leafletUserData = user;

        const lat = initialLat || 55.7558;
        const lng = initialLng || 37.6173;

        const latInput = document.getElementById('map-lat-input');
        const lngInput = document.getElementById('map-lng-input');
        if (latInput) latInput.value = lat;
        if (lngInput) lngInput.value = lng;
        wire.set('editLat', lat);
        wire.set('editLng', lng);

        window.leafletMap = L.map('user-map').setView([lat, lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(window.leafletMap);

        const popupContent = window.createPopupContent(user, initialAddress || 'Адрес не определён');
        
        window.leafletMarker = L.marker([lat, lng], { draggable: true })
            .addTo(window.leafletMap)
            .bindPopup(popupContent)
            .openPopup();

        window.leafletMarker.on('dragend', function(e) {
            const pos = window.leafletMarker.getLatLng();
            
            window.livewireWire.set('editLat', pos.lat);
            window.livewireWire.set('editLng', pos.lng);

            const latInput = document.getElementById('map-lat-input');
            const lngInput = document.getElementById('map-lng-input');
            if (latInput) latInput.value = pos.lat;
            if (lngInput) lngInput.value = pos.lng;

            const addressElement = document.getElementById('user-address');
            if (addressElement) addressElement.innerText = 'Не определён';
            
            if (window.leafletMarker && window.leafletUserData) {
                const popup = window.leafletMarker.getPopup();
                if (popup) {
                    popup.setContent(window.createPopupContent(window.leafletUserData, 'Не определён'));
                }
            }

            window.livewireWire.set('address', null);
        });
        
        window.updateMarkerFromInputs = function() {
            if (!window.leafletMarker) return;
            const newLat = parseFloat(latInput.value);
            const newLng = parseFloat(lngInput.value);

            if (!isNaN(newLat) && !isNaN(newLng)) {
                window.leafletMarker.setLatLng([newLat, newLng]);
                window.leafletMap.setView([newLat, newLng], 13);
            }
        };

        window.fetchAddress = async function() {
            if (!window.leafletMarker || !window.livewireWire) return;

            const pos = window.leafletMarker.getLatLng();
            const addressElement = document.getElementById('user-address');
            if (addressElement) addressElement.innerText = 'Определение адреса...';

            const newAddress = await window.livewireWire.call('updateAddressFromCoords', pos.lat, pos.lng);
            
            if (newAddress) {
                if (addressElement) addressElement.innerText = newAddress;
                const popup = window.leafletMarker.getPopup();
                if (popup) popup.setContent(window.createPopupContent(window.leafletUserData, newAddress));
                window.livewireWire.set('address', newAddress);
            } else {
                if (addressElement) addressElement.innerText = 'Адрес не определён';
            }
        };

        setTimeout(() => {
            if (window.leafletMap) window.leafletMap.invalidateSize();
        }, 100);
    };
</script>
@endscript