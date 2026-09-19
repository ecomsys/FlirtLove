@props(['user'])

@php
    $p = $user->profile;
    if (!$p) return;

    // Хелпер для полей из конфига (enum)
    $get = function($key, $value) {
        if ($value === null || (int)$value === 0) return null;
        return config("profile_fields.options.{$key}.{$value}");
    };

    // Хелпер для текстовых полей
    $getText = function($value) {
        return (empty($value)) ? null : $value;
    };

    // Хелпер для JSON массивов (возвращаем массив строк для бейджей)
    $getArray = function($key, $values) {
        if (empty($values) || !is_array($values)) return [];
        $mapped = array_map(fn($id) => config("profile_fields.options.{$key}.{$id}"), $values);
        return array_filter($mapped);
    };

    // Формируем "Внешность"
    $appearance = [];
    if (!empty($p->height)) $appearance[] = $p->height . ' см';
    if (!empty($p->body_type) && (int)$p->body_type !== 0) {
        $bt = config("profile_fields.options.body_type.{$p->body_type}");
        if ($bt) $appearance[] = mb_strtolower($bt);
    }
    $appearanceStr = empty($appearance) ? null : implode(', ', $appearance);

    // Подготавливаем массивы для бейджей
    $languages = $getArray('languages', $p->languages);
    $sports = $getArray('sports', $p->sports);
@endphp

<div class="mb-6 border-t border-border pt-4">
    <h3 class="text-base font-medium text-accent-foreground mb-2">Личная информация</h3>
       
 
    <div class="flex flex-col gap-y-3">
        
        @if($appearanceStr)
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Внешность</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $appearanceStr }}</span>
        </div>
        @endif

        @if($val = $get('relationship_status', $p->relationship_status))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Отношения</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $get('children_status', $p->children_status))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Дети</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $get('pets', $p->pets))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Домашние животные</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $get('housing', $p->housing))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Жилищные условия</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $get('has_car', $p->has_car))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Наличие автомобиля</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $get('education_level', $p->education_level))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Образование</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $getText($p->institution))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Учебное заведение</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $getText($p->institution_year))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Год выпуска</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $get('income', $p->income))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Доход</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $getText($p->activity))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Сфера деятельности</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $getText($p->position))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Должность</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $get('smoking', $p->smoking))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Курение</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if($val = $get('alcohol', $p->alcohol))
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5">Алкоголь</span>
            <span class="text-foreground text-sm sm:w-3/5">{{ $val }}</span>
        </div>
        @endif

        @if(!empty($languages))
        <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5 sm:mt-1.5">Знание языков</span>
            <div class="flex flex-wrap gap-1.5 sm:w-3/5">
                @foreach($languages as $lang)
                    <x-ui.badge variant="default" class="bg-blue-400">{{ $lang }}</x-ui.badge>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($sports))
        <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
            <span class="text-muted-foreground text-sm font-light sm:w-2/5 sm:mt-1.5">Спорт</span>
            <div class="flex flex-wrap gap-1.5 sm:w-3/5">
                @foreach($sports as $sport)
                    <x-ui.badge variant="default" class="bg-blue-400">{{ $sport }}</x-ui.badge>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>