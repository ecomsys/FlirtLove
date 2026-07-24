<?php
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use App\Notifications\UserBanned;
use App\Notifications\PhotoModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; 

new #[Layout('layouts.admin')] class extends Component 
{
    public User $user;

    public function mount(User $user): void
    {
        //  Загружаем альбомы с фото, а также жалобы
        $this->user = $user->load([
            'albums' => function ($query) {
                $query->with(['photos' => function ($q) {
                    $q->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc');
                }])->orderBy('is_default', 'desc')->orderBy('name');
            },
            'receivedReports',
            'sentReports',
        ]);
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

        $this->dispatch('show-toast', 
            type: 'success', 
            message: $newStatus ? "Пользователь {$this->user->name} забанен" : "Пользователь {$this->user->name} разбанен"
        );
        
        $this->dispatch('$refresh');
    }

    public function deletePhoto(int $photoId): void
    {
        $photo = $this->user->photos()->find($photoId);
        if ($photo) {
            DB::transaction(function () use ($photo) {
                if ($photo->path && !filter_var($photo->path, FILTER_VALIDATE_URL)) {
                    Storage::disk('public')->delete($photo->path);
                }
                $photo->delete();
            });

            $this->user->notify(new PhotoModerated(
                $photoId,
                $this->user->id,
                'deleted',
                1
            ));

            //  Перезагружаем альбомы с фото
            $this->user->load([
                'albums' => function ($query) {
                    $query->with(['photos' => function ($q) {
                        $q->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc');
                    }])->orderBy('is_default', 'desc')->orderBy('name');
                },
            ]);

            $this->dispatch('show-toast', type: 'success', message: 'Фото удалено. Уведомление отправлено пользователю.');
            $this->dispatch('$refresh');
        }
    }

    public function setPrimaryPhoto(int $photoId): void
    {
        $this->user->photos()->update(['is_primary' => false]);
        $this->user
            ->photos()
            ->where('id', $photoId)
            ->update(['is_primary' => true]);

        //  Перезагружаем альбомы с фото
        $this->user->load([
            'albums' => function ($query) {
                $query->with(['photos' => function ($q) {
                    $q->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc');
                }])->orderBy('is_default', 'desc')->orderBy('name');
            },
        ]);

        $this->dispatch('show-toast', type: 'success', message: 'Фото установлено как основное');
        $this->dispatch('$refresh');
    }
}; ?>

<!-- Инициализируем Alpine с активной первой вкладкой -->
<div x-data="{ tab: 'profile' }" class="space-y-6">

    <!-- Шапка профиля -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.users.index') }}"
                class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2 flex-wrap">
                    {{ $user->name }}
                    <span class="text-xs text-muted-foreground font-normal">(ID: {{ $user->id }})</span>
                    @if ($user->is_admin)
                        <x-ui.badge variant="default" size="sm">Admin</x-ui.badge>
                    @endif
                    @if ($user->is_banned)
                        <x-ui.badge variant="destructive" size="sm">Забанен</x-ui.badge>
                    @endif
                </h1>
                <p class="text-sm text-muted-foreground">{{ $user->email }}</p>
            </div>
        </div>

        <!-- Кнопки действий -->
        <div class="flex items-center gap-2">
            @if (!$user->is_admin)
                <x-ui.button wire:click="toggleBan" wire:loading.attr="disabled"
                    variant="{{ $user->is_banned ? 'success' : 'destructive' }}">
                    <span wire:loading.remove
                        wire:target="toggleBan">{{ $user->is_banned ? 'Разбанить' : 'Забанить' }}</span>
                    <span wire:loading wire:target="toggleBan">Обработка...</span>
                </x-ui.button>
            @endif
            <x-ui.button variant="outline" onclick="window.location.href='mailto:{{ $user->email }}'">
                <x-lucide-mail class="w-4 h-4" />
                Написать
            </x-ui.button>           
        </div>
    </div>

    <!-- Меню вкладок -->
    <div class="border-b border-border">
        <nav class="flex gap-4 flex-wrap">
            <button @click="tab = 'profile'"
                :class="tab === 'profile' ? 'border-primary text-primary' :
                    'border-transparent text-muted-foreground hover:text-foreground'"
                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-user class="w-4 h-4 inline mr-1" />
                Профиль
            </button>
            <button @click="tab = 'photos'"
                :class="tab === 'photos' ? 'border-primary text-primary' :
                    'border-transparent text-muted-foreground hover:text-foreground'"
                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-image class="w-4 h-4 inline mr-1" />
                Фотографии ({{ $user->photos->count() }})
            </button>
            <button @click="tab = 'sessions'"
                :class="tab === 'sessions' ? 'border-primary text-primary' :
                    'border-transparent text-muted-foreground hover:text-foreground'"
                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-clock class="w-4 h-4 inline mr-1" />
                Активность
            </button>
            <button @click="tab = 'moderation'"
                :class="tab === 'moderation' ? 'border-primary text-primary' :
                    'border-transparent text-muted-foreground hover:text-foreground'"
                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-shield class="w-4 h-4 inline mr-1" />
                Модерация
            </button>
        </nav>
    </div>

    <!-- Контент вкладок -->
    <div class="bg-card border border-border rounded-lg p-6">

        <!-- ============================================ -->
        <!-- Вкладка 1: ПРОФИЛЬ -->
        <!-- ============================================ -->
        <div x-show="tab === 'profile'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Левая колонка -->
                <div class="space-y-4">
                    <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                        <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="xl"
                            userId="{{ $user->id }}" showStatus="true" />
                        <div>
                            <p class="font-medium">{{ $user->name }}</p>
                            <p class="text-xs text-muted-foreground">{{ $user->email }}</p>
                            <p class="text-xs text-muted-foreground">ID: {{ $user->id }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Пол</p>
                            <p class="text-sm font-medium mt-1 flex items-center gap-1">
                                @if ($user->gender === 'male')
                                    <x-lucide-mars class="w-4 h-4 text-blue-500" />
                                    Мужской
                                @elseif($user->gender === 'female')
                                    <x-lucide-venus class="w-4 h-4 text-pink-500" />
                                    Женский
                                @else
                                    <span class="text-muted-foreground">Не указан</span>
                                @endif
                            </p>
                        </div>
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Дата рождения</p>
                            <p class="text-sm font-medium mt-1">
                                {{ $user->birth_date ? $user->birth_date->format('d.m.Y') : 'Не указана' }}
                                @if ($user->birth_date)
                                    <span class="text-xs text-muted-foreground">({{ $user->birth_date->age }}
                                        лет)</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Город</p>
                            <p class="text-sm font-medium mt-1 flex items-center gap-1">
                                <x-lucide-map-pin class="w-4 h-4 text-muted-foreground" />
                                {{ $user->city ?? 'Не указан' }}
                            </p>
                        </div>
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Цель знакомства</p>
                            <p class="text-sm font-medium mt-1">
                                @switch($user->dating_goal)
                                    @case('friends')
                                        🤝 Поиск друзей
                                    @break

                                    @case('romantic')
                                        ❤️ Романтические отношения
                                    @break

                                    @case('family')
                                        👨‍👩‍👦 Создание семьи
                                    @break

                                    @case('casual')
                                        🔥 Свободные отношения
                                    @break

                                    @default
                                        <span class="text-muted-foreground">Не указана</span>
                                @endswitch
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Правая колонка -->
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Статус</p>
                            <div class="mt-1">
                                @if ($user->is_banned)
                                    <x-ui.badge variant="destructive">Забанен</x-ui.badge>
                                @else
                                    <x-ui.badge variant="success">Активен</x-ui.badge>
                                @endif
                            </div>
                        </div>
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Онбординг</p>
                            <div class="mt-1">
                                @if ($user->has_completed_onboarding)
                                    <x-ui.badge variant="success">Завершен</x-ui.badge>
                                @else
                                    <x-ui.badge variant="warning">Не завершен</x-ui.badge>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Фото</p>
                            <p class="text-sm font-medium mt-1">{{ $user->photos->count() }} шт.</p>
                        </div>
                        <div class="p-4 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground uppercase">Дата регистрации</p>
                            <p class="text-sm font-medium mt-1">{{ $user->created_at->format('d.m.Y H:i') }}</p>
                            <p class="text-xs text-muted-foreground">{{ $user->created_at->diffForHumans() }}</p>
                        </div>
                    </div>

                    <div class="p-4 bg-muted/20 rounded-lg border border-border">
                        <p class="text-xs text-muted-foreground uppercase">О себе</p>
                        <p class="text-sm mt-1 text-muted-foreground">
                            {{ $user->about ?? 'Пользователь не заполнил информацию о себе' }}
                        </p>
                    </div>

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
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- Вкладка 2: ФОТОГРАФИИ (с группировкой по альбомам) -->
        <!-- ============================================ -->
        <div x-show="tab === 'photos'" style="display: none;">
            @if ($user->albums->isNotEmpty())
                @foreach ($user->albums as $album)
                    <div class="mb-8 last:mb-0">
                        <h3 class="text-lg font-medium mb-3 flex items-center gap-2">
                            <x-lucide-folder class="w-5 h-5 text-muted-foreground" />
                            {{ $album->name }}
                            @if ($album->is_default)
                                <x-ui.badge variant="secondary" size="xs">Основной</x-ui.badge>
                            @endif
                            <span class="text-sm text-muted-foreground font-normal">
                                ({{ $album->photos->count() }} фото)
                            </span>
                        </h3>
                        @if ($album->photos->isNotEmpty())
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                @foreach ($album->photos as $photo)
                                    <div wire:key="photo-{{ $photo->id }}" class="relative group border border-border rounded-lg overflow-hidden bg-muted">
                                        <a href="{{ $photo->large_url ?? $photo->url }}" data-fancybox="user-gallery"
                                            data-caption="{{ $user->name }} - {{ $album->name }}">
                                            <img src="{{ $photo->thumb_url ?? $photo->url }}"
                                                class="w-full aspect-square object-cover group-hover:scale-105 transition-transform duration-300"
                                                alt="User Photo">
                                        </a>

                                        <!-- Бейджи -->
                                        <div class="absolute top-2 left-2 flex flex-col gap-1">
                                            @if ($photo->is_primary)
                                                <x-ui.badge size="xs">Аватар</x-ui.badge>
                                            @endif
                                            @if ($photo->is_intimate)
                                                <x-ui.badge variant="destructive" size="xs">18+</x-ui.badge>
                                            @endif
                                        </div>

                                        <!-- Статус модерации -->
                                        <div class="absolute top-2 right-2">
                                            @if ($photo->status == 'approved')
                                                <x-ui.badge variant="success" size="xs">Одобрено</x-ui.badge>
                                            @elseif($photo->status == 'pending')
                                                <x-ui.badge variant="warning" size="xs">Ожидает</x-ui.badge>
                                            @else
                                                <x-ui.badge variant="destructive" size="xs">Отклонено</x-ui.badge>
                                            @endif
                                        </div>

                                        <!-- Действия при наведении -->
                                        <div
                                            class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                            @if (!$photo->is_primary)
                                                <x-ui.button wire:click="setPrimaryPhoto({{ $photo->id }})" variant="default"
                                                    size="icon-sm" class="text-white hover:bg-white/20" title="Сделать основным">
                                                    <x-lucide-star class="w-5 h-5" />
                                                </x-ui.button>
                                            @endif
                                            <x-ui.button wire:click="deletePhoto({{ $photo->id }})" variant="destructive"
                                                size="icon-sm" class="text-white hover:bg-destructive/60" title="Удалить">
                                                <x-lucide-trash-2 class="w-5 h-5" />
                                            </x-ui.button>
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

        <!-- ============================================ -->
        <!-- Вкладка 3: АКТИВНОСТЬ -->
        <!-- ============================================ -->
        <div x-show="tab === 'sessions'" style="display: none;" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                        <x-lucide-clock class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Последний вход</p>
                        <p class="text-sm font-medium mt-1">
                            {{ $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : 'Никогда' }}</p>
                        @if ($user->last_login_at)
                            <p class="text-xs text-muted-foreground">{{ $user->last_login_at->diffForHumans() }}</p>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                        <x-lucide-wifi class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">IP-адрес</p>
                        <p class="text-sm font-medium mt-1 font-mono">{{ $user->last_login_ip ?? 'Нет данных' }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                        <x-lucide-calendar class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Дата регистрации</p>
                        <p class="text-sm font-medium mt-1">{{ $user->created_at->format('d.m.Y H:i') }}</p>
                        <p class="text-xs text-muted-foreground">{{ $user->created_at->diffForHumans() }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg border border-border">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                        <x-lucide-edit class="w-5 h-5" />
                    </div>
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
                    <span>Для детального отслеживания устройств и геолокации рекомендуется использовать пакет <code
                            class="px-1 py-0.5 bg-muted rounded text-[10px]">jenssegers/agent</code></span>
                </p>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- Вкладка 4: МОДЕРАЦИЯ -->
        <!-- ============================================ -->
        <div x-show="tab === 'moderation'" style="display: none;" class="space-y-4">            

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-muted/30 rounded-lg border border-border">
                    <p class="text-xs text-muted-foreground uppercase">Всего фото</p>
                    <p class="text-2xl font-bold mt-1">{{ $user->photos->count() }}</p>
                </div>
                <div class="p-4 bg-muted/30 rounded-lg border border-border">
                    <p class="text-xs text-muted-foreground uppercase">На модерации</p>
                    <p class="text-2xl font-bold mt-1 text-yellow-500">
                        {{ $user->photos()->where('status', 'pending')->count() }}</p>
                </div>
            </div>

           <div class="p-4 bg-muted/30 rounded-lg border border-border">
                <p class="text-xs text-muted-foreground uppercase mb-2">Статистика жалоб</p>
                
                @php
                    $receivedCount = $user->reports_received_count ?? 0;
                    $sentCount = $user->reports_sent_count ?? 0;
                    
                    $receivedVariant = $receivedCount == 0 ? 'success' : ($receivedCount <= 5 ? 'warning' : 'destructive');
                    $sentVariant = $sentCount == 0 ? 'success' : ($sentCount <= 5 ? 'warning' : 'destructive');
                @endphp
                
                <div class="space-y-2">
                    <p class="text-sm text-muted-foreground flex items-center gap-2">
                        Жалоб на пользователя:
                        <x-ui.badge :variant="$receivedVariant" size="sm">{{ $receivedCount }}</x-ui.badge>
                    </p>
                    
                    <p class="text-sm text-muted-foreground flex items-center gap-2">
                        Жалоб от пользователя:
                        <x-ui.badge :variant="$sentVariant" size="sm">{{ $sentCount }}</x-ui.badge>
                    </p>
                </div>
            </div>

            <div class="p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                <p class="text-sm text-yellow-700 dark:text-yellow-400 flex items-center gap-2">
                    <x-lucide-alert-triangle class="w-5 h-5" />
                    <span>Если пользователь нарушает правила, вы можете заблокировать его через кнопку "Забанить" в
                        шапке профиля</span>
                </p>
            </div>
        </div>

    </div>
</div>