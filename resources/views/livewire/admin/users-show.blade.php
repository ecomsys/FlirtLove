<?php

use App\Models\User;
use App\Models\Photo;
use App\Models\PhotoComment;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use App\Notifications\UserBanned;
use App\Notifications\PhotoModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

new #[Layout('layouts.admin')] class extends Component 
{
    public User $user;

    public $address = null;
    public $editLat = null;
    public $editLng = null;
    
    // Свойство для хранения фото на модерации
    public $pendingPhotos;

    public function mount(User $user): void
    {
        // 1. Загружаем альбомы с фото и жалобы (только pending для модерации)
        $this->user = $user->load([
            'albums' => function ($query) {
                $query->with(['photos' => function ($q) {
                    $q->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc');
                }])->orderBy('is_default', 'desc')->orderBy('name');
            },
            'receivedReports' => fn($q) => $q->where('status', 'pending')->with('user'),
            'sentReports' => fn($q) => $q->where('status', 'pending')->with('reportedUser'),
            'photoComments' => fn($q) => $q->where('status', 'pending')->with('photo'),
        ]);
        
        // 2. Оптимизация: Считаем фото на модерации одним запросом в БД
        $this->user->loadCount(['photos as pending_photos_count' => function ($q) {
            $q->where('status', 'pending');
        }]);

        // 3. Загружаем фото на модерации для быстрого доступа
        $this->pendingPhotos = $user->photos()->where('status', 'pending')->get();

        // 4. Берем адрес из БД, если его нет — запрашиваем по координатам
        if ($user->latitude && $user->longitude) {
            $this->address = $user->address ?? $this->getAddressFromCoords($user->latitude, $user->longitude);
        }

        $this->editLat = $user->latitude;
        $this->editLng = $user->longitude;
    }

    /**
     * Полное физическое удаление всех версий фото с диска.
     */
    private function deletePhotoFiles(Photo $photo): void
    {
        $paths = [$photo->path, $photo->path_original, $photo->path_large, $photo->path_medium, $photo->path_thumb];
        foreach ($paths as $path) {
            if ($path && !filter_var($path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function getAddressFromCoords(float $lat, float $lng): ?string
    {
        $cacheKey = "address_{$lat}_{$lng}";
        return Cache::remember($cacheKey, 86400, function () use ($lat, $lng) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'LoveClone/1.0',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat, 'lon' => $lng, 'format' => 'json', 'zoom' => 18,
                ]);

                if ($response->successful()) {
                    return $response->json()['display_name'] ?? null;
                }
                return null;
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    public function updateAddressFromCoords(float $lat, float $lng): ?string
    {
        $this->address = $this->getAddressFromCoords($lat, $lng);
        return $this->address;
    }

    public function updateLocation(): void
    {
        $this->validate([
            'editLat' => 'required|numeric|between:-90,90',
            'editLng' => 'required|numeric|between:-180,180',
        ]);

        $actualAddress = $this->getAddressFromCoords((float) $this->editLat, (float) $this->editLng);

        $this->user->latitude = $this->editLat;
        $this->user->longitude = $this->editLng;
        $this->user->address = $actualAddress;
        $this->user->save();

        DB::table('users')->where('id', $this->user->id)->update([
            'location' => DB::raw("ST_SetSRID(ST_MakePoint({$this->editLng}, {$this->editLat}), 4326)"),
        ]);

        $this->address = $actualAddress;
        $this->dispatch('show-toast', type: 'success', message: 'Координаты и адрес обновлены');
    }

    public function toggleBan(): void
    {
        if ($this->user->id === Auth::id()) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя забанить самого себя!');
            return;
        }

        $newStatus = !$this->user->is_banned;
        DB::transaction(function () use ($newStatus) {
            $this->user->update(['is_banned' => $newStatus]);
        });

        $this->user->notify(new UserBanned($newStatus));
        $this->dispatch('show-toast', type: 'success', message: $newStatus ? "Пользователь {$this->user->name} забанен" : "Пользователь {$this->user->name} разбанен");
        $this->dispatch('$refresh');
    }

    public function deletePhoto(int $photoId): void
    {
        $photo = $this->user->photos()->find($photoId);
        if ($photo) {
            DB::transaction(function () use ($photo) {
                $this->deletePhotoFiles($photo);
                $photo->delete();
            });

            $this->user->notify(new PhotoModerated($photoId, $this->user->id, 'deleted', 1));
            $this->user->load(['albums' => function ($query) {
                $query->with(['photos' => function ($q) {
                    $q->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc');
                }])->orderBy('is_default', 'desc')->orderBy('name');
            }]);
            $this->dispatch('show-toast', type: 'success', message: 'Фото удалено.');
            
            // Обновляем данные для вкладки модерации
            $this->pendingPhotos = $this->user->photos()->where('status', 'pending')->get();
        }
    }

    public function setPrimaryPhoto(int $photoId): void
    {
        DB::transaction(function () use ($photoId) {
            $this->user->photos()->update(['is_primary' => false]);
            $this->user->photos()->where('id', $photoId)->update(['is_primary' => true]);
        });

        $this->user->load(['albums' => function ($query) {
            $query->with(['photos' => function ($q) {
                $q->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc');
            }])->orderBy('is_default', 'desc')->orderBy('name');
        }]);

        $this->dispatch('show-toast', type: 'success', message: 'Фото установлено как основное');
    }
}; 
?>

<!-- Инициализируем Alpine с памятью вкладок (localStorage) -->
<div x-data='{
    tab: localStorage.getItem("admin_user_tab") || "profile",
    initMapTab() {
        if (this.tab === "map") {
            this.$nextTick(() => {
                window.setupMap(
                    @json($user->latitude),
                    @json($user->longitude),
                    @json($address),
                    @json($user)
                );
            });
        }
    },
    init() {
        this.$watch("tab", value => {
            localStorage.setItem("admin_user_tab", value);
            this.initMapTab();
        });
        this.initMapTab();
    }
}' class="space-y-6">
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
                <x-lucide-image class="w-4 h-4 inline mr-1" /> Фотографии ({{ $user->photos->count() }})
            </button>
            <button @click="tab = 'sessions'" :class="tab === 'sessions' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-clock class="w-4 h-4 inline mr-1" /> Активность
            </button>
            <button @click="tab = 'moderation'" :class="tab === 'moderation' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-shield class="w-4 h-4 inline mr-1" /> Модерация
                @if($this->pendingPhotos->count() > 0 || $user->photoComments->count() > 0 || $user->receivedReports->count() > 0)
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
                
                <!-- ЛЕВАЯ КОЛОНКА (Анкета) -->
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
                                @if ($user->is_invisible) <x-ui.badge variant="secondary" size="xs">Невидимка</x-ui.badge> @endif
                            </div>
                        </div>
                    </div>

                    <!-- Пол и Возраст -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Пол</p>
                            <p class="text-sm font-medium mt-1 flex items-center gap-1">
                                @if ($user->gender === 'male') <x-lucide-mars class="w-4 h-4 text-blue-500" /> Мужской
                                @elseif($user->gender === 'female') <x-lucide-venus class="w-4 h-4 text-pink-500" /> Женский
                                @else <span class="text-muted-foreground">Не указан</span> @endif
                            </p>
                        </div>
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Дата рождения</p>
                            <p class="text-sm font-medium mt-1">
                                {{ $user->birth_date ? $user->birth_date->format('d.m.Y') : 'Не указана' }}
                                @if ($user->birth_date) <span class="text-xs text-muted-foreground">({{ $user->birth_date->age }} лет)</span> @endif
                            </p>
                        </div>
                    </div>

                    <!-- Локация и Цель -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Город / Страна</p>
                            <p class="text-sm font-medium mt-1 flex items-center gap-1">
                                <x-lucide-map-pin class="w-4 h-4 text-muted-foreground" />
                                {{ $user->city ?? 'Не указан' }} {{ $user->country ? ', ' . $user->country : '' }}
                            </p>
                        </div>
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Цель знакомства</p>
                            <p class="text-sm font-medium mt-1">
                                @switch($user->dating_goal)
                                    @case('friends') 🤝 Поиск друзей @break
                                    @case('romantic') ❤️ Романтические отношения @break
                                    @case('family') 👨‍👩‍👦 Создание семьи @break
                                    @case('casual') 🔥 Свободные отношения @break
                                    @default <span class="text-muted-foreground">Не указана</span>
                                @endswitch
                            </p>
                        </div>
                    </div>

                    <!-- О себе и Кого ищу -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase">О себе</p>
                        <p class="text-sm mt-1 text-muted-foreground">{{ $user->bio ?? 'Пользователь не заполнил информацию о себе' }}</p>
                    </div>
                    
                    @if($user->looking_for)
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase">Кого я хочу найти</p>
                        <p class="text-sm mt-1 text-muted-foreground">{{ $user->looking_for }}</p>
                    </div>
                    @endif

                    <!-- Интересы -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase">Интересы</p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @if ($user->interests && is_array($user->interests) && count($user->interests) > 0)
                                @foreach ($user->interests as $interest)
                                    <x-ui.badge variant="secondary" size="xs" wire:key="interest-{{ $loop->index }}">{{ $interest }}</x-ui.badge>
                                @endforeach
                            @else
                                <span class="text-sm text-muted-foreground">Не указаны</span>
                            @endif
                        </div>
                    </div>

                    @php
                        // Подключаем словари и хелперы для вывода Личной информации
                        $options = config('profile_options');
                        $pd = $user->profile_details;
                        $getOptionName = function($key, $id) use ($options) { return $options[$key][$id] ?? null; };
                        $getOptionsList = function($key, $ids) use ($options) {
                            if (!is_array($ids)) return [];
                            return array_filter(array_map(function($id) use ($options, $key) { return $options[$key][$id] ?? null; }, $ids));
                        };
                    @endphp

                    <!-- Внешность (из profile_details) -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Внешность</p>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            @if($user->height) <div><span class="text-muted-foreground">Рост:</span> <span class="font-medium">{{ $user->height }} см</span></div> @endif
                            @if($user->weight) <div><span class="text-muted-foreground">Вес:</span> <span class="font-medium">{{ $user->weight }} кг</span></div> @endif
                            @if(!empty($pd['body_type'])) <div><span class="text-muted-foreground">Телосложение:</span> <span class="font-medium">{{ $getOptionName('body_type', $pd['body_type']) }}</span></div> @endif
                            @if(!empty($pd['eye_color'])) <div><span class="text-muted-foreground">Цвет глаз:</span> <span class="font-medium">{{ $getOptionName('eye_color', $pd['eye_color']) }}</span></div> @endif
                            @if(!empty($pd['hair_color'])) <div><span class="text-muted-foreground">Цвет волос:</span> <span class="font-medium">{{ $getOptionName('hair_color', $pd['hair_color']) }}</span></div> @endif
                            @if(!empty($pd['body_decorations']))
                                <div class="col-span-2"><span class="text-muted-foreground">На теле есть:</span> <span class="font-medium">{{ implode(', ', $getOptionsList('body_decorations', $pd['body_decorations'])) }}</span></div>
                            @endif
                            @if($user->zodiac_sign) <div><span class="text-muted-foreground">Знак зодиака:</span> <span class="font-medium">{{ $user->zodiac_sign }}</span></div> @endif
                        </div>
                    </div>

                    <!-- Личная жизнь и Быт -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Личная жизнь и Быт</p>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            @if(!empty($pd['relationship_status'])) <div><span class="text-muted-foreground">Отношения:</span> <span class="font-medium">{{ $getOptionName('relationship_status', $pd['relationship_status']) }}</span></div> @endif
                            @if(!empty($pd['children_status'])) <div><span class="text-muted-foreground">Дети:</span> <span class="font-medium">{{ $getOptionName('children_status', $pd['children_status']) }}</span></div> @endif
                            @if(!empty($pd['pets'])) <div><span class="text-muted-foreground">Животные:</span> <span class="font-medium">{{ $getOptionName('pets', $pd['pets']) }}</span></div> @endif
                            @if(!empty($pd['housing'])) <div><span class="text-muted-foreground">Жилье:</span> <span class="font-medium">{{ $getOptionName('housing', $pd['housing']) }}</span></div> @endif
                            @if(!empty($pd['has_car'])) <div><span class="text-muted-foreground">Автомобиль:</span> <span class="font-medium">{{ $getOptionName('has_car', $pd['has_car']) }}</span></div> @endif
                        </div>
                    </div>

                    <!-- Работа и Образование -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Работа и Образование</p>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            @if(!empty($pd['education_level'])) <div><span class="text-muted-foreground">Образование:</span> <span class="font-medium">{{ $getOptionName('education_level', $pd['education_level']) }}</span></div> @endif
                            @if(!empty($pd['institution'])) <div class="col-span-2"><span class="text-muted-foreground">Учебное заведение:</span> <span class="font-medium">{{ $pd['institution'] }}</span></div> @endif
                            @if(!empty($pd['graduation_year'])) <div><span class="text-muted-foreground">Год выпуска:</span> <span class="font-medium">{{ $pd['graduation_year'] }}</span></div> @endif
                            @if(!empty($pd['industry'])) <div><span class="text-muted-foreground">Сфера:</span> <span class="font-medium">{{ $pd['industry'] }}</span></div> @endif
                            @if(!empty($pd['occupation'])) <div><span class="text-muted-foreground">Должность:</span> <span class="font-medium">{{ $pd['occupation'] }}</span></div> @endif
                            @if(!empty($pd['income'])) <div><span class="text-muted-foreground">Доход:</span> <span class="font-medium">{{ $getOptionName('income', $pd['income']) }}</span></div> @endif
                        </div>
                    </div>

                    <!-- Привычки и Увлечения -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Привычки и Увлечения</p>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            @if(!empty($pd['smoking'])) <div><span class="text-muted-foreground">Курение:</span> <span class="font-medium">{{ $getOptionName('smoking', $pd['smoking']) }}</span></div> @endif
                            @if(!empty($pd['alcohol'])) <div><span class="text-muted-foreground">Алкоголь:</span> <span class="font-medium">{{ $getOptionName('alcohol', $pd['alcohol']) }}</span></div> @endif
                            @if(!empty($pd['languages']))
                                <div class="col-span-2"><span class="text-muted-foreground">Языки:</span> <span class="font-medium">{{ implode(', ', $getOptionsList('languages', $pd['languages'])) }}</span></div>
                            @endif
                            @if(!empty($pd['sports']))
                                <div class="col-span-2"><span class="text-muted-foreground">Спорт:</span> <span class="font-medium">{{ implode(', ', $getOptionsList('sports', $pd['sports'])) }}</span></div>
                            @endif
                        </div>
                    </div>

                    <!-- Предпочтения в поиске -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Предпочтения в поиске</p>
                        <div class="text-xs space-y-2">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Ищет:</span> 
                                <span class="font-medium">
                                    @if ($user->preferred_gender == 'any') Всех
                                    @elseif ($user->preferred_gender == 'male') Мужчин
                                    @elseif ($user->preferred_gender == 'female') Женщин
                                    @else {{ $user->preferred_gender }} @endif
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Возраст:</span> 
                                <span class="font-medium">{{ $user->preferred_age_min }} - {{ $user->preferred_age_max }} лет</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Радиус:</span> 
                                <span class="font-medium">{{ $user->preferred_distance_km }} км</span>
                            </div>
                            @if($user->search_filters)
                                @php $sf = $user->search_filters; @endphp
                                @if(!empty($sf['height_from']) || !empty($sf['height_to']))
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Рост:</span> 
                                        <span class="font-medium">{{ $sf['height_from'] ?? '-' }} - {{ $sf['height_to'] ?? '-' }} см</span>
                                    </div>
                                @endif
                                @if(!empty($sf['is_verified_only']))<div class="text-muted-foreground flex justify-between">Дополнительно:<span class="text-primary"> Только верифицированные</span></div> @endif
                                @if(!empty($sf['is_premium_only']))<div class="text-muted-foreground flex justify-between">Дополнительно:<span class="text-yellow-500"> Только Premium</span></div> @endif
                            @endif
                        </div>
                    </div>
                </div>

                <!-- ПРАВАЯ КОЛОНКА (Статусы и метрики) -->
                <div class="space-y-4">
                    <!-- Просмотры и Лайки -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Просмотры</p>
                            <p class="text-sm font-medium mt-1">{{ $user->profile_views }} шт.</p>
                        </div>
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Лайки</p>
                            <p class="text-sm font-medium mt-1">{{ $user->likes_count }} шт.</p>
                        </div>
                    </div>                                      

                    <!-- Подписка и Email -->
                    <div class="grid grid-cols-2 gap-4">                       
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Подписка</p>
                            <div class="mt-1 flex items-center gap-2 flex-wrap">
                                @if ($user->has_active_premium)
                                    <x-ui.badge variant="warning"><x-lucide-crown class="w-3 h-3 inline mr-1" />Premium</x-ui.badge>
                                    @if($user->premium_expires_at)
                                        <span class="text-xs text-muted-foreground">до {{ $user->premium_expires_at->format('d.m.Y') }}</span>
                                    @else
                                        <span class="text-xs text-muted-foreground">Бессрочно</span>
                                    @endif
                                @else
                                    <x-ui.badge variant="secondary">Бесплатный</x-ui.badge>
                                @endif
                            </div>
                        </div>
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Email</p>
                            <div class="mt-1">
                                @if ($user->email_verified_at) <x-ui.badge variant="success">Подтвержден</x-ui.badge>
                                @else <x-ui.badge variant="destructive">Не подтвержден</x-ui.badge> @endif
                            </div>
                        </div>
                    </div>

                    <!-- Онбординг и Статус -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Онбординг</p>
                            <div class="mt-1">
                                @if ($user->has_completed_onboarding) <x-ui.badge variant="success">Завершен</x-ui.badge>
                                @else <x-ui.badge variant="warning">Не завершен</x-ui.badge> @endif
                            </div>
                        </div>
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Статус аккаунта</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @if ($user->is_banned) <x-ui.badge variant="destructive">Забанен</x-ui.badge> @endif
                                @if ($user->is_deactivated) <x-ui.badge variant="warning">Заморожен</x-ui.badge> @endif
                                @if (!$user->is_banned && !$user->is_deactivated) <x-ui.badge variant="success">Активен</x-ui.badge> @endif
                            </div>
                        </div>                            
                    </div>

                    <!-- Активность -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Дата регистрации</p>
                            <p class="text-sm font-medium mt-1">{{ $user->created_at->format('d.m.Y H:i') }}</p>
                            <p class="text-xs text-muted-foreground">{{ $user->created_at->diffForHumans() }}</p>
                        </div>   
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Последний визит</p>
                            <p class="text-sm font-medium mt-1">
                                {{ $user->last_seen ? $user->last_seen->diffForHumans() : 'Никогда' }}
                            </p>
                            @if($user->is_online) <span class="text-xs text-green-500">● Онлайн</span> @endif
                        </div>                                    
                    </div>

                    <!-- Суперлайки и Фото -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Суперлайки</p>
                            <p class="text-sm font-medium mt-1">{{ $user->superlikes_remaining }} шт.</p>
                        </div>  
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Фото</p>
                            <p class="text-sm font-medium mt-1">{{ $user->photos->count() }} шт.</p>
                        </div>
                    </div>                

                    <!-- Настройки чата -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Настройки чата</p>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Фильтр сообщений:</span>
                            @if ($user->chat_filter_enabled) <x-ui.badge variant="info" size="xs">Включен</x-ui.badge>
                            @else <span class="font-medium text-muted-foreground">Доступен всем</span> @endif
                        </div>
                        @if ($user->chat_filter_enabled && $user->chat_filter_settings)
                            @php $cf = $user->chat_filter_settings; @endphp
                            <div class="mt-2 text-xs space-y-1 border-t border-border pt-2">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Принимает от:</span> 
                                    <span>{{ $cf['gender'] ?? 'any' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Возраст:</span> 
                                    <span>{{ $cf['age_from'] ?? 18 }} - {{ $cf['age_to'] ?? 99 }}</span>
                                </div>
                                @if(!empty($cf['is_verified_only'])) <div class="text-primary">Только верифицированные</div> @endif
                                @if(!empty($cf['is_premium_only'])) <div class="text-yellow-500">Только Premium</div> @endif
                            </div>
                        @endif
                    </div>

                    <!-- Приватность и Контент -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Приватность</p>
                        <div class="text-sm space-y-1">
                            <div class="flex justify-between"><span class="text-muted-foreground">Режим "Невидимка":</span> @if($user->is_invisible) <span class="text-green-500">Вкл</span> @else <span class="text-muted-foreground">Выкл</span> @endif</div>
                            <div class="flex justify-between"><span class="text-muted-foreground">Скрывать 18+ фото:</span> @if($user->hide_intimate) <span class="text-green-500">Да</span> @else <span class="text-muted-foreground">Нет</span> @endif</div>
                            <div class="flex justify-between"><span class="text-muted-foreground">Комментарии к фото:</span> @if($user->disable_photo_comments) <span class="text-destructive">Запрещены</span> @else <span class="text-muted-foreground">Разрешены</span> @endif</div>
                            <div class="flex justify-between"><span class="text-muted-foreground">Скрыт из поиска:</span> @if($user->hide_from_search) <span class="text-yellow-500">Да</span> @else <span class="text-muted-foreground">Нет</span> @endif</div>
                        </div>
                    </div>

                    <!-- Уведомления -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Уведомления</p>
                        @php $es = $user->email_settings; @endphp
                        <div class="text-sm space-y-1">
                            <div class="flex justify-between"><span class="text-muted-foreground">Push (браузер):</span> @if($user->push_enabled) <span class="text-green-500">Вкл</span> @else <span class="text-muted-foreground">Выкл</span> @endif</div>
                            <div class="text-xs mt-2 border-t border-border pt-2 text-muted-foreground">Уведомления на Email:</div>
                            <div class="flex justify-between"><span>Сообщения:</span> @if($es['on_message']) <span>✅</span> @else <span>❌</span> @endif</div>
                            <div class="flex justify-between"><span>Лайки:</span> @if($es['on_like']) <span>✅</span> @else <span>❌</span> @endif</div>
                            <div class="flex justify-between"><span>Просмотры:</span> @if($es['on_view']) <span>✅</span> @else <span>❌</span> @endif</div>
                            <div class="flex justify-between"><span>Модерация:</span> @if($es['on_photo_moderated']) <span>✅</span> @else <span>❌</span> @endif</div>
                            <div class="flex justify-between"><span>Рассылки:</span> @if($es['on_broadcast']) <span>✅</span> @else <span>❌</span> @endif</div>
                        </div>
                    </div>

                    <!-- Системные данные -->
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase mb-2">Системные данные</p>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-muted-foreground">Локаль:</span> 
                                <span class="font-mono">{{ $user->locale }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Тема:</span> 
                                <span class="font-mono">{{ $user->theme }}</span>
                            </div>
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
                                                    @if (!$photo->is_primary)
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
                    <div>
                        <p class="text-xs text-muted-foreground">IP-адрес</p>
                        <p class="text-sm font-medium mt-1 font-mono">{{ $user->last_login_ip ?? 'Нет данных' }}</p>
                    </div>
                </div>
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
            <div class="text-xs text-muted-foreground p-4 border border-dashed border-border rounded-lg bg-muted/10">
                <p class="flex items-center gap-2">
                    <x-lucide-info class="w-4 h-4" />
                    <span>Для детального отслеживания устройств и геолокации рекомендуется использовать пакет <code class="px-1 py-0.5 bg-muted rounded text-[10px]">jenssegers/agent</code></span>
                </p>
            </div>
        </div>

        <!-- Вкладка 4: МОДЕРАЦИЯ (ИНФОРМАТИВНАЯ) -->
        <div x-show="tab === 'moderation'" style="display: none;" class="space-y-6">
            
            <!-- 1. Фото на модерации (Информативно) -->
            <div>
                <h3 class="text-lg font-medium mb-3 flex items-center gap-2">
                    <x-lucide-image class="w-5 h-5 text-muted-foreground" />
                    Фото ожидают модерации 
                    <x-ui.badge variant="warning" size="xs">{{ $pendingPhotos->count() }}</x-ui.badge>
                </h3>
                @if ($pendingPhotos->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        @foreach ($pendingPhotos as $photo)
                            <div class="relative group border border-border rounded-lg overflow-hidden bg-muted">
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

            <!-- 2. Комментарии на модерации (Информативно) -->
            <div>
                <h3 class="text-lg font-medium mb-3 flex items-center gap-2">
                    <x-lucide-message-circle class="w-5 h-5 text-muted-foreground" />
                    Комментарии ожидают модерации 
                    <x-ui.badge variant="warning" size="xs">{{ $user->photoComments->count() }}</x-ui.badge>
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

            <!-- 3. Жалобы (Информативно) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Входящие жалобы -->
                <div class="p-4 bg-muted/30 rounded-lg border border-border">
                    <h4 class="text-sm font-medium mb-2 flex items-center gap-2">
                        <x-lucide-alert-triangle class="w-4 h-4 text-destructive" />
                        Жалобы на пользователя 
                        <x-ui.badge variant="destructive" size="xs">{{ $user->receivedReports->count() }}</x-ui.badge>
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

                <!-- Исходящие жалобы -->
                <div class="p-4 bg-muted/30 rounded-lg border border-border">
                    <h4 class="text-sm font-medium mb-2 flex items-center gap-2">
                        <x-lucide-flag class="w-4 h-4 text-muted-foreground" />
                        Жалобы от пользователя 
                        <x-ui.badge variant="secondary" size="xs">{{ $user->sentReports->count() }}</x-ui.badge>
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
            
            <!-- Предупреждение -->
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
                            <x-ui.input wire:model="editLat" label="Широта" type="number" step="any" oninput="updateMarkerFromInputs()" />
                            <x-ui.input wire:model="editLng" label="Долгота" type="number" step="any" oninput="updateMarkerFromInputs()" />
                            <x-ui.button wire:click="updateLocation" wire:loading.attr="disabled" class="w-full">
                                <span wire:loading.remove wire:target="updateLocation">Сохранить</span>
                                <span wire:loading wire:target="updateLocation" class="flex items-center justify-center gap-3">
                                    <x-ui.spinner class="w-4 h-4 inline" /> Сохранение...
                                </span>
                            </x-ui.button>
                        </div>
                    </div>
                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground">🔁 Перетащите маркер, чтобы изменить позицию, или введите координаты вручную.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    if (typeof Fancybox !== 'undefined') {
        Fancybox.defaults.Hash = false;
    }

    window.leafletMap = null;
    window.leafletMarker = null;

    window.setupMap = function(initialLat, initialLng, initialAddress, user) {
        const container = document.getElementById('user-map');
        if (!container) return;

        if (window.leafletMap) {
            setTimeout(() => {
                window.leafletMap.invalidateSize();
                if (window.leafletMarker) window.leafletMarker.openPopup();
            }, 50);
            return;
        }

        const lat = initialLat || 55.7558;
        const lng = initialLng || 37.6173;

        window.leafletMap = L.map('user-map').setView([lat, lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(window.leafletMap);

        const createPopupContent = (u, addr) => {
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

        window.leafletMarker = L.marker([lat, lng], { draggable: true })
            .addTo(window.leafletMap)
            .bindPopup(createPopupContent(user, initialAddress))
            .openPopup();

        window.leafletMarker.on('dragend', async function(e) {
            const pos = window.leafletMarker.getLatLng();
            @this.set('editLat', pos.lat);
            @this.set('editLng', pos.lng);

            const addressElement = document.getElementById('user-address');
            if (addressElement) addressElement.innerText = 'Определение адреса...';

            const newAddress = await @this.call('updateAddressFromCoords', pos.lat, pos.lng);
            
            if (newAddress) {
                if (addressElement) addressElement.innerText = newAddress;
                const popup = window.leafletMarker.getPopup();
                if (popup) popup.setContent(createPopupContent(user, newAddress));
            } else {
                if (addressElement) addressElement.innerText = 'Адрес не определён';
            }
        });

        window.updateMarkerFromInputs = function() {
            const inputLat = document.querySelector('input[wire\\:model="editLat"]');
            const inputLng = document.querySelector('input[wire\\:model="editLng"]');
            if (!inputLat || !inputLng || !window.leafletMarker) return;

            const newLat = parseFloat(inputLat.value);
            const newLng = parseFloat(inputLng.value);

            if (!isNaN(newLat) && !isNaN(newLng)) {
                window.leafletMarker.setLatLng([newLat, newLng]);
                window.leafletMap.setView([newLat, newLng], 13);
            }
        };

        setTimeout(() => {
            if (window.leafletMap) window.leafletMap.invalidateSize();
        }, 100);
    };
</script>
@endpush