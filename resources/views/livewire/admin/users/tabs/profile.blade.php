<?php

use App\Models\User;
use Livewire\Volt\Component;

new class extends Component 
{
    public User $user;

    /**
     * Хелпер для перевода ID опций в текст (из конфига profile_options).
     */
    public function getOptionLabel(string $type, int|string|null $value): ?string
    {
        if (is_null($value) || $value === '') return 'Нет ответа';
        return config("profile_options.{$type}.{$value}", 'Нет ответа');
    }

    /**
     * Хелпер для вывода JSON-массивов опций (языки, спорт) в виде бейджей.
     */
    public function getArrayLabels(string $type, ?array $values): array
    {
        if (empty($values)) return [];
        return array_map(fn($id) => $this->getOptionLabel($type, $id), $values);
    }

    /**
     * Хелпер для перевода пола (муж/жен/любой).
     */
    public function getGenderLabel(?string $gender): string
    {
        return match($gender) {
            'male' => 'Мужчины',
            'female' => 'Женщины',
            default => 'Любой'
        };
    }

    /**
     * Хелпер для вывода бейджей расширенных фильтров поиска.
     */
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

    /**
     * Хелпер для знака зодиака (т.к. его нет в конфиге).
     */
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
        
        {{-- Основная информация --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-user class="w-3.5 h-3.5" /> Основная информация
            </p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Пол</span>
                    <span class="text-sm font-medium">
                        {{ $user->profile?->gender === 'male' ? 'Мужской' : ($user->profile?->gender === 'female' ? 'Женский' : 'Не указан') }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Возраст</span>
                    <span class="text-sm font-medium">{{ $user->profile?->age ? $user->profile->age . ' лет' : 'Не указан' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Дата рождения</span>
                    <span class="text-sm font-medium">{{ $user->profile?->birth_date ? $user->profile->birth_date->format('d.m.Y') : '—' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Страна</span>
                    <span class="text-sm font-medium">{{ $user->profile?->country ?? 'Не указана' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Город</span>
                    <span class="text-sm font-medium">{{ $user->profile?->city ?? 'Не указан' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Цель знакомства</span                    @php $goalEnum = \App\Enums\DatingGoal::tryFrom($user->profile?->dating_goal ?? ''); @endphp
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
                    <span class="text-sm font-medium">{{ $this->getZodiacLabel($user->profile?->zodiac_sign) }}</span>
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
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('body_type', $user->profile?->body_type) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Цвет глаз</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('eye_color', $user->profile?->eye_color) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Цвет волос</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('hair_color', $user->profile?->hair_color) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Рост / Вес</span>
                    <span class="text-sm font-medium">
                        {{ $user->profile?->height ? $user->profile->height . ' см' : '—' }} / {{ $user->profile?->weight ? $user->profile->weight . ' кг' : '—' }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Украшения / Особенности</span>
                    <div class="flex flex-wrap gap-1 justify-end max-w-[60%]">
                        @foreach($this->getArrayLabels('body_decorations', $user->profile?->body_decorations) as $label)
                            <x-ui.badge variant="secondary" size="xs">{{ $label }}</x-ui.badge>
                        @endforeach
                        @if(empty($user->profile?->body_decorations)) <span class="text-sm font-medium">Нет</span> @endif
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
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('relationship_status', $user->profile?->relationship_status) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Дети</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('children_status', $user->profile?->children_status) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Домашние животные</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('pets', $user->profile?->pets) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Жилье</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('housing', $user->profile?->housing) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Автомобиль</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('has_car', $user->profile?->has_car) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Курение</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('smoking', $user->profile?->smoking) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Алкоголь</span>
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('alcohol', $user->profile?->alcohol) }}</span>
                </div>
            </div>
        </div>

        {{-- О себе --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-1 font-semibold flex items-center gap-1.5">
                <x-lucide-message-square class="w-3.5 h-3.5" /> О себе
            </p>
            @if($user->profile?->headline)
                <p class="text-sm font-medium text-foreground mb-1">"{{ $user->profile->headline }}"</p>
            @endif
            <p class="text-sm text-muted-foreground">{{ $user->profile?->bio ?? 'Пусто' }}</p>
            
            @if($user->profile?->looking_for)
                <div class="mt-3 pt-3 border-t border-border/50">
                    <p class="text-xs text-muted-foreground mb-1">Кого я ищу:</p>
                    <p class="text-sm text-muted-foreground">{{ $user->profile->looking_for }}</p>
                </div>
            @endif
        </div>

        {{-- Автопортрет (JSON) --}}
        @if(!empty($user->profile?->self_portrait))
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-brush class="w-3.5 h-3.5" /> Автопортрет
            </p>
            <div class="space-y-2">
                @foreach($user->profile->self_portrait as $key => $value)
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
                    <span class="text-sm font-medium">{{ $this->getOptionLabel('education_level', $user->profile?->education) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Учебное заведение</span>
                    <span class="text-sm font-medium">{{ $user->profile?->institution ?? '—' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Год выпуска</span>
                    <span class="text-sm font-medium">{{ $user->profile?->institution_year ?? '—' }}</span>
                </div>             
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Сфера деятельности</span>
                    <span class="text-sm font-medium">{{ $user->profile?->activity ?? '—' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Должность</span>
                    <span class="text-sm font-medium">{{ $user->profile?->position ?? '—' }}</span>
                </div>
            </div>
        </div>

        {{-- Интересы (JSON) --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold flex items-center gap-1.5">
                <x-lucide-star class="w-3.5 h-3.5" /> Интересы
            </p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($this->getArrayLabels('sports', $user->profile?->sports) as $label)
                    <x-ui.badge variant="default" size="xs">{{ $label }}</x-ui.badge>
                @endforeach
                @foreach($user->profile?->interests ?? [] as $interest)
                    <x-ui.badge variant="secondary" size="xs">{{ $interest }}</x-ui.badge>
                @endforeach
                @if(empty($user->profile?->sports) && empty($user->profile?->interests))
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
                @foreach($this->getArrayLabels('languages', $user->profile?->languages) as $label)
                    <x-ui.badge variant="warning" size="xs">{{ $label }}</x-ui.badge>
                @endforeach
                @if(empty($user->profile?->languages))
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
                    @if ($user->has_active_premium)
                        <x-ui.badge variant="warning" size="xs"><x-lucide-crown class="w-3 h-3 inline mr-1" />Premium</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" size="xs">Бесплатный</x-ui.badge>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Верификация</p>
                    @if ($user->is_verified) <x-ui.badge variant="success" size="xs">Верифицирован</x-ui.badge>
                    @else <x-ui.badge variant="destructive" size="xs">Не верифицирован</x-ui.badge> @endif
                </div>
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Онбординг</p>
                    @if ($user->has_completed_onboarding) <x-ui.badge variant="success" size="xs">Пройден</x-ui.badge>
                    @else <x-ui.badge variant="warning" size="xs">Не завершен</x-ui.badge> @endif
                </div>
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Email</p>
                    @if ($user->email_verified_at) <x-ui.badge variant="success" size="xs">Подтвержден</x-ui.badge>
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
                    <span class="text-sm font-medium">{{ $user->created_at->format('d.m.Y H:i') }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Последний визит</span>
                    @if($user->is_online)
                        <span class="text-sm font-medium text-green-500 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> В сети
                        </span>
                    @else
                        <span class="text-sm font-medium">{{ $user->last_seen ? $user->last_seen->diffForHumans() : 'Никогда' }}</span>
                    @endif
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">IP адрес</span>
                    <span class="text-sm font-mono font-medium">{{ $user->last_login_ip ?? 'Нет данных' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Device ID</span>
                    <span class="text-sm font-mono font-medium truncate ml-4">{{ $user->device_id ?? 'Нет данных' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">ОС устройства</span>
                    <span class="text-sm font-mono font-medium truncate ml-4">{{ $user->device_os ?? 'Нет данных' }}</span>
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
                    <span class="text-sm font-medium">{{ $this->getGenderLabel($user->preferences?->preferred_gender) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Возраст</span>
                    <span class="text-sm font-medium">
                        {{ $user->preferences?->preferred_age_min ?? 18 }} - {{ $user->preferences?->preferred_age_max ?? 99 }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Радиус поиска</span>
                    <span class="text-sm font-medium">
                        {{ $user->preferences?->preferred_distance_km ? $user->preferences->preferred_distance_km . ' км' : 'Не ограничен' }}
                    </span>
                </div>
            </div>
            
            {{-- Дополнительные фильтры поиска (JSON) --}}
            @php $searchFilterLabels = $this->getSearchFilterLabels($user->preferences?->search_filters); @endphp
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
                    @if($user->preferences?->chat_filter_enabled)
                        <x-ui.badge variant="success" size="xs">Включен</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" size="xs">Выключен</x-ui.badge>
                    @endif
                </div>
                @if($user->preferences?->chat_filter_enabled)
                    @php $chatFilters = $user->preferences->chat_filter_settings; @endphp
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
                    @if($user->preferences?->is_invisible)
                        <x-ui.badge variant="warning" size="xs">Включена</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" size="xs">Выключена</x-ui.badge>
                    @endif
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Скрыт из поиска</span>
                    @if($user->preferences?->hide_from_search)
                        <x-ui.badge variant="destructive" size="xs">Да</x-ui.badge>
                    @else
                        <x-ui.badge variant="success" size="xs">Нет</x-ui.badge>
                    @endif
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Скрывать 18+ фото</span>
                    @if($user->preferences?->hide_intimate)
                        <x-ui.badge variant="success" size="xs">Да</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" size="xs">Нет</x-ui.badge>
                    @endif
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Запрет коммент. фото</span>
                    @if($user->preferences?->disable_photo_comments)
                        <x-ui.badge variant="destructive" size="xs">Да</x-ui.badge>
                    @else
                        <x-ui.badge variant="success" size="xs">Нет</x-ui.badge>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>