<?php

use App\Models\AdminLog;
use App\Models\User;
use App\Notifications\ProfileFieldCleared;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component 
{
    // ПРИНИМАЕМ ТОЛЬКО ID (Спасает от 404/500 при рендере)
    public int $userId;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    // ДОСТАЕМ ЮЗЕРА (с удаленными, чтобы не падать)
    #[Computed]
    public function user(): User
    {
        return User::withTrashed()
            ->with(['profile', 'preferences'])
            ->findOrFail($this->userId);
    }

    // Слушаем обновления из родительского компонента
    #[On('user-action-performed')] 
    public function refreshUser(): void
    {
        unset($this->user);
    }

    /**
     * Очистка текстового поля модератором с логированием и уведомлением.
     */
    public function clearProfileField(string $field): void
    {
        $allowedFields = ['headline', 'bio', 'looking_for'];
        if (!in_array($field, $allowedFields)) return;

        $user = $this->user;
        $profile = $user->profile;
        if (!$profile) return;

        $oldValue = $profile->{$field};
        if (empty($oldValue)) {
            $this->dispatch('show-toast', type: 'error', message: 'Это поле уже пустое.');
            return;
        }

        // Очищаем поле в БД
        $profile->update([$field => null]);

        // Логируем в AdminLog (before/after)
        AdminLog::record('profile.clear_field', $user, auth()->user(), [$field => $oldValue], [$field => null]);

        // Отправляем юзеру уведомление (если он не удален)
        if (!$user->trashed()) {
            $user->notify(new ProfileFieldCleared($field));
        }

        $fieldLabels = ['headline' => 'Заголовок', 'bio' => 'О себе', 'looking_for' => 'Кого я ищу'];
        $this->dispatch('show-toast', type: 'success', message: "Поле '{$fieldLabels[$field]}' очищено. Юзеру отправлено уведомление.");
        
        $this->refreshUser();
    }

    // ============================================
    // ХЕЛПЕРЫ ДЛЯ ВЫВОДА (Твои методы)
    // ============================================

    public function getOptionLabel(string $type, int|string|null $value): ?string
    {
        if (is_null($value) || $value === '') return 'Нет ответа';
        return config("profile_options.{$type}.{$value}", 'Нет ответа');
    }

    public function getArrayLabels(string $type, ?array $values): array
    {
        if (empty($values)) return [];
        return array_map(fn($id) => $this->getOptionLabel($type, $id), $values);
    }

    public function getGenderLabel(?string $gender): string
    {
        return match($gender) {
            'male' => 'Мужчины',
            'female' => 'Женщины',
            default => 'Любой'
        };
    }

    public function getSearchFilterLabels(?array $filters): array
    {
        if (empty($filters)) return [];
        $labels = [];
        foreach ($filters as $key => $value) {
            if (is_null($value) || $value === '' || $value === false) continue;
            if (in_array($key, ['body_type', 'eye_color', 'hair_color', 'smoking', 'alcohol', 'education_level'])) {
                $labels[] = $this->getOptionLabel($key, $value);
            } elseif ($key === 'is_verified_only') {
                $labels[] = 'Только верифицированные';
            } elseif ($key === 'is_premium_only') {
                $labels[] = 'Только VIP';
            } elseif ($key === 'height_from') {
                $labels[] = 'Рост от ' . $value . ' см';
            } elseif ($key === 'height_to') {
                $labels[] = 'Рост до ' . $value . ' см';
            } else {
                $labels[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . (is_bool($value) ? 'Да' : $value);
            }
        }
        return $labels;
    }

    public function getZodiacLabel(?int $sign): string
    {
        $signs = [
            0 => 'Нет ответа', 1 => 'Овен', 2 => 'Телец', 3 => 'Близнецы', 4 => 'Рак', 
            5 => 'Лев', 6 => 'Дева', 7 => 'Весы', 8 => 'Скорпион', 9 => 'Стрелец', 
            10 => 'Козерог', 11 => 'Водолей', 12 => 'Рыбы'
        ];
        return $signs[$sign ?? 0] ?? 'Нет ответа';
    }
}; 
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    {{-- ЛЕВАЯ КОЛОНКА --}}
    <div class="space-y-4">

           {{-- Тексты анкеты (С кнопками очистки) --}}
            <div class="p-4 bg-muted/20 rounded-lg border border-border space-y-1">
                <p class="text-xs text-muted-foreground uppercase font-semibold flex items-center gap-1.5">
                    <x-lucide-message-square class="w-3.5 h-3.5" /> Тексты анкеты
                </p>

                <div class="divide-y divide-border/50">
                {{-- Заголовок --}}
                <div class="py-3">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-medium text-muted-foreground uppercase">Заголовок</span>
                        @if($this->user->profile?->headline)
                            <x-ui.button wire:click="clearProfileField('headline')" wire:confirm="Очистить заголовок? Юзеру уйдет уведомление." variant="ghost" size="xs" class="text-red-500 hover:text-red-400 gap-1 h-6 px-2">
                                <x-lucide-trash-2 class="w-3 h-3" /> Удалить
                            </x-ui.button>
                        @endif
                    </div>
                    <p class="text-sm {{ $this->user->profile?->headline ? 'text-foreground font-medium' : 'text-muted-foreground/50 italic' }}">
                        @if($this->user->profile?->headline) "{{ $this->user->profile->headline }}" @else Не указано @endif
                    </p>
                </div>

                {{-- О себе --}}
                <div class="py-3">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-medium text-muted-foreground uppercase">О себе</span>
                        @if($this->user->profile?->bio)
                            <x-ui.button wire:click="clearProfileField('bio')" wire:confirm="Очистить поле 'О себе'? Юзеру уйдет уведомление." variant="ghost" size="xs" class="text-red-500 hover:text-red-400 gap-1 h-6 px-2">
                                <x-lucide-trash-2 class="w-3 h-3" /> Удалить
                            </x-ui.button>
                        @endif
                    </div>
                    <p class="text-sm {{ $this->user->profile?->bio ? 'text-foreground' : 'text-muted-foreground/50 italic' }}">
                        {{ $this->user->profile?->bio ?? 'Не указано' }}
                    </p>
                </div>

                {{-- Кого я ищу --}}
                <div class="py-3">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-medium text-muted-foreground uppercase">Кого я ищу</span>
                        @if($this->user->profile?->looking_for)
                            <x-ui.button wire:click="clearProfileField('looking_for')" wire:confirm="Очистить поле 'Кого я ищу'? Юзеру уйдет уведомление." variant="ghost" size="xs" class="text-red-500 hover:text-red-400 gap-1 h-6 px-2">
                                <x-lucide-trash-2 class="w-3 h-3" /> Удалить
                            </x-ui.button>
                        @endif
                    </div>
                    <p class="text-sm {{ $this->user->profile?->looking_for ? 'text-foreground' : 'text-muted-foreground/50 italic' }}">
                        {{ $this->user->profile?->looking_for ?? 'Не указано' }}
                    </p>
                </div>
                </div>
            </div>

        
        {{-- Основная информация --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-user class="w-3.5 h-3.5" /> Основная информация
            </p>        

            <div class="divide-y divide-border/50">            
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Пол</span>
                    <span class="text-sm font-medium">
                        {{ $this->user->profile?->gender === 'male' ? 'Мужской' : ($this->user->profile?->gender === 'female' ? 'Женский' : 'Не указан') }}
                    </span>
                </div>

                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Возраст</span>
                    <span class="text-sm font-medium">{{ $this->user->profile?->age ? $this->user->profile->age . ' лет' : 'Не указан' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Дата рождения</span>
                    <span class="text-sm font-medium">{{ $this->user->profile?->birth_date ? $this->user->profile->birth_date->format('d.m.Y') : '—' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Страна</span>
                    <span class="text-sm font-medium">{{ $this->user->profile?->country ?? 'Не указана' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Город</span>
                    <span class="text-sm font-medium">{{ $this->user->profile?->city ?? 'Не указан' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Цель знакомства</span>
                    @php $goalEnum = \App\Enums\DatingGoal::tryFrom($this->user->profile?->dating_goal ?? ''); @endphp
                    @if($goalEnum)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $goalEnum->color() }}">
                            {{ $goalEnum->label() }}
                        </span>
                    @else
                        <span class="text-sm font-medium">Не указана</span>
                    @endif
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Знак зодиака</span>
                    <span class="text-sm font-medium">{{ $this->getZodiacLabel($this->user->profile?->zodiac_sign) }}</span>
                </div>
            </div>
        </div>

        {{-- Внешность --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-eye class="w-3.5 h-3.5" /> Внешность
            </p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Телосложение</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('body_type', $this->user->profile?->body_type) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Цвет глаз</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('eye_color', $this->user->profile?->eye_color) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Цвет волос</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('hair_color', $this->user->profile?->hair_color) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Рост / Вес</span>
                    <span class="text-sm font-medium">
                        {{ $this->user->profile?->height ? $this->user->profile->height . ' см' : '—' }} / {{ $this->user->profile?->weight ? $this->user->profile->weight . ' кг' : '—' }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Украшения / Особенности</span>
                    <div class="flex flex-wrap gap-1 justify-end max-w-[60%]">
                        @foreach($this->getArrayLabels('body_decorations', $this->user->profile?->body_decorations) as $label)
                            <x-ui.badge variant="secondary" size="xs">{{ $label }}</x-ui.badge>
                        @endforeach
                        @if(empty($this->user->profile?->body_decorations)) <span class="text-sm font-medium">Нет</span> @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Личная жизнь и привычки --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-heart-handshake class="w-3.5 h-3.5" /> Личная жизнь и привычки
            </p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Семейное положение</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('relationship_status', $this->user->profile?->relationship_status) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Дети</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('children_status', $this->user->profile?->children_status) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Домашние животные</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('pets', $this->user->profile?->pets) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Жилье</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('housing', $this->user->profile?->housing) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Автомобиль</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('has_car', $this->user->profile?->has_car) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Курение</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('smoking', $this->user->profile?->smoking) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Алкоголь</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('alcohol', $this->user->profile?->alcohol) }}</span>
                </div>
            </div>
        </div>

     
        {{-- Автопортрет (JSON) --}}
        @if(!empty($this->user->profile?->self_portrait))
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-brush class="w-3.5 h-3.5" /> Автопортрет
            </p>
            <div class="space-y-2">
                @foreach($this->user->profile->self_portrait as $key => $value)
                    <div>
                        <span class="text-xs text-muted-foreground capitalize">{{ str_replace('_', ' ', $key) }}</span>
                        <p class="text-sm font-medium">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ПРАВАЯ КОЛОНКА --}}
    <div class="space-y-4">

        {{-- Работа и образование --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-briefcase class="w-3.5 h-3.5" /> Работа и образование
            </p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Образование</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('education_level', $this->user->profile?->education) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Учебное заведение</span>
                    <span class="text-sm font-medium">{{ $this->user->profile?->institution ?? '—' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Год выпуска</span>
                    <span class="text-sm font-medium">{{ $this->user->profile?->institution_year ?? '—' }}</span>
                </div>             
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Сфера деятельности</span>
                    <span class="text-sm font-medium">{{ $this->user->profile?->activity ?? '—' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Должность</span>
                    <span class="text-sm font-medium">{{ $this->user->profile?->position ?? '—' }}</span>
                </div>
            </div>
        </div>

        {{-- Интересы (JSON) --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-star class="w-3.5 h-3.5" /> Интересы
            </p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($this->getArrayLabels('sports', $this->user->profile?->sports) as $label)
                    <x-ui.badge variant="default" size="xs">{{ $label }}</x-ui.badge>
                @endforeach
                @foreach($this->user->profile?->interests ?? [] as $interest)
                    <x-ui.badge variant="secondary" size="xs">{{ $interest }}</x-ui.badge>
                @endforeach
                @if(empty($this->user->profile?->sports) && empty($this->user->profile?->interests))
                    <span class="text-sm font-medium">Не указаны</span>
                @endif
            </div>
        </div>

        {{-- Языки (JSON) --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-languages class="w-3.5 h-3.5" /> Языки
            </p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($this->getArrayLabels('languages', $this->user->profile?->languages) as $label)
                    <x-ui.badge variant="warning" size="xs">{{ $label }}</x-ui.badge>
                @endforeach
                @if(empty($this->user->profile?->languages))
                    <span class="text-sm font-medium">Не указаны</span>
                @endif
            </div>
        </div>

        {{-- Статусы аккаунта --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-shield-check class="w-3.5 h-3.5" /> Статусы аккаунта
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Подписка</p>
                    @if ($this->user->has_active_premium)
                        <x-ui.badge variant="warning" size="xs"><x-lucide-crown class="w-3 h-3 inline mr-1" />Premium</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" size="xs">Бесплатный</x-ui.badge>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Верификация</p>
                    @if ($this->user->is_verified) <x-ui.badge variant="success" size="xs">Верифицирован</x-ui.badge>
                    @else <x-ui.badge variant="destructive" size="xs">Не верифицирован</x-ui.badge> @endif
                </div>
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Онбординг</p>
                    @if ($this->user->has_completed_onboarding) <x-ui.badge variant="success" size="xs">Пройден</x-ui.badge>
                    @else <x-ui.badge variant="warning" size="xs">Не завершен</x-ui.badge> @endif
                </div>
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Email</p>
                    @if ($this->user->email_verified_at) <x-ui.badge variant="success" size="xs">Подтвержден</x-ui.badge>
                    @else <x-ui.badge variant="destructive" size="xs">Не подтвержден</x-ui.badge> @endif
                </div>
            </div>
        </div>

        {{-- Системные данные --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-server class="w-3.5 h-3.5" /> Системные данные
            </p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Регистрация</span>
                    <span class="text-sm font-medium">{{ $this->user->created_at->format('d.m.Y H:i') }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Последний визит</span>
                    @if($this->user->is_online)
                        <span class="text-sm font-medium text-green-500 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> В сети
                        </span>
                    @else
                        <span class="text-sm font-medium">{{ $this->user->last_seen ? $this->user->last_seen->diffForHumans() : 'Никогда' }}</span>
                    @endif
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">IP адрес</span>
                    <span class="text-sm font-mono font-medium">{{ $this->user->last_login_ip ?? 'Нет данных' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Device ID</span>
                    <span class="text-sm font-mono font-medium truncate ml-4">{{ $this->user->device_id ?? 'Нет данных' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">ОС устройства</span>
                    <span class="text-sm font-mono font-medium truncate ml-4">{{ $this->user->device_os ?? 'Нет данных' }}</span>
                </div>
            </div>
        </div>

        {{-- Настройки поиска --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-search class="w-3.5 h-3.5" /> Настройки поиска
            </p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Искомый пол</span>
                    <span class="text-sm font-medium">{{ $this->getGenderLabel($this->user->preferences?->preferred_gender) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Возраст</span>
                    <span class="text-sm font-medium">
                        {{ $this->user->preferences?->preferred_age_min ?? 18 }} - {{ $this->user->preferences?->preferred_age_max ?? 99 }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Радиус поиска</span>
                    <span class="text-sm font-medium">
                        {{ $this->user->preferences?->preferred_distance_km ? $this->user->preferences->preferred_distance_km . ' км' : 'Не ограничен' }}
                    </span>
                </div>
            </div>
            
            {{-- Дополнительные фильтры поиска (JSON) --}}
            @php $searchFilterLabels = $this->getSearchFilterLabels($this->user->preferences?->search_filters); @endphp
            @if(!empty($searchFilterLabels))
                <div class="mt-3 pt-3 border-t border-border/50">
                    <p class="text-xs text-muted-foreground mb-2">Дополнительные фильтры:</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($searchFilterLabels as $label)
                            <x-ui.badge variant="default" size="xs">{{ $label }}</x-ui.badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Настройки чата --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-message-circle class="w-3.5 h-3.5" /> Настройки чата
            </p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Фильтр сообщений</span>
                    @if($this->user->preferences?->chat_filter_enabled)
                        <x-ui.badge variant="success" size="xs">Включен</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" size="xs">Выключен</x-ui.badge>
                    @endif
                </div>
                @if($this->user->preferences?->chat_filter_enabled)
                    @php $chatFilters = $this->user->preferences->chat_filter_settings; @endphp
                    <div class="flex justify-between items-center py-1.5">
                        <span class="text-xs text-muted-foreground">Принимать от пола</span>
                        <span class="text-sm font-medium">{{ $this->getGenderLabel($chatFilters['gender'] ?? 'any') }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1.5">
                        <span class="text-xs text-muted-foreground">Возраст</span>
                        <span class="text-sm font-medium">
                            {{ $chatFilters['age_from'] ?? 18 }} - {{ $chatFilters['age_to'] ?? 99 }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-1.5">
                        <span class="text-xs text-muted-foreground">Только верифицированные</span>
                        @if($chatFilters['is_verified_only'] ?? false)
                            <x-ui.badge variant="success" size="xs">Да</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" size="xs">Нет</x-ui.badge>
                        @endif
                    </div>
                    <div class="flex justify-between items-center py-1.5">
                        <span class="text-xs text-muted-foreground">Только VIP</span>
                        @if($chatFilters['is_premium_only'] ?? false)
                            <x-ui.badge variant="warning" size="xs">Да</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" size="xs">Нет</x-ui.badge>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Настройки приватности --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-shield class="w-3.5 h-3.5" /> Настройки приватности
            </p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Невидимка (VIP)</span>
                    @if($this->user->preferences?->is_invisible)
                        <x-ui.badge variant="warning" size="xs">Включена</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" size="xs">Выключена</x-ui.badge>
                    @endif
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Скрыт из поиска</span>
                    @if($this->user->preferences?->hide_from_search)
                        <x-ui.badge variant="destructive" size="xs">Да</x-ui.badge>
                    @else
                        <x-ui.badge variant="success" size="xs">Нет</x-ui.badge>
                    @endif
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Скрывать 18+ фото</span>
                    @if($this->user->preferences?->hide_intimate)
                        <x-ui.badge variant="success" size="xs">Да</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" size="xs">Нет</x-ui.badge>
                    @endif
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Запрет коммент. фото</span>
                    @if($this->user->preferences?->disable_photo_comments)
                        <x-ui.badge variant="destructive" size="xs">Да</x-ui.badge>
                    @else
                        <x-ui.badge variant="success" size="xs">Нет</x-ui.badge>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>