{{-- <!-- С картинкой -->
<x-ui.avatar src="{{ Auth::user()->avatar }}" name="{{ Auth::user()->name }}" size="md" />

<!-- Без картинки - будет показывать инициалы с цветом -->
<x-ui.avatar name="{{ Auth::user()->name }}" size="lg" />

<!-- Только инициалы -->
<x-ui.avatar name="John Doe" size="xl" />

<!-- Размеры -->
<x-ui.avatar name="John Doe" size="sm" />   <!-- 32x32 -->
<x-ui.avatar name="John Doe" size="md" />   <!-- 40x40 -->
<x-ui.avatar name="John Doe" size="lg" />   <!-- 48x48 -->
<x-ui.avatar name="John Doe" size="xl" />   <!-- 64x64 --> 

<!-- Для текущего админа -->
<x-ui.avatar 
    src="{{ Auth::user()->avatar_url }}" 
    name="{{ Auth::user()->name }}" 
    size="md"
    userId="{{ Auth::id() }}"
    showStatus="true"
/>

<!-- Для пользователя в списке -->
<x-ui.avatar 
    src="{{ $user->avatar_url }}" 
    name="{{ $user->name }}" 
    size="sm"
    userId="{{ $user->id }}"
    showStatus="true"
/>
--}}

@props([
    'src' => null,
    'name' => null,
    'size' => 'md',
    'class' => '',
    'userId' => null,
    'showStatus' => false,
    'isOnline' => null, 
])

@php
    $sizes = [
        'sm' => 'w-8 h-8',
        'md' => 'w-10 h-10',
        'lg' => 'w-12 h-12',
        'xl' => 'w-16 h-16',
    ];
    
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    
    $statusSizes = [
        'sm' => 'w-2 h-2',
        'md' => 'w-2.5 h-2.5',
        'lg' => 'w-3 h-3',
        'xl' => 'w-4 h-4',
    ];
    $statusSize = $statusSizes[$size] ?? $statusSizes['md'];
    
    $initials = '?';
    $bgColor = 'bg-primary/10';
    $textColor = 'text-primary';
    
    if ($name) {
        $name = trim($name);
        $words = preg_split('/\s+/', $name);
        
        if (count($words) >= 2) {
            $first = mb_substr($words[0], 0, 1);
            $last = mb_substr($words[count($words) - 1], 0, 1);
            $initials = mb_strtoupper($first . $last);
        } else {
            $initials = mb_strtoupper(mb_substr($name, 0, 2));
        }
        
        $colors = [
            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
            'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
            'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
            'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-400',
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-400',
            'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
            'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
        ];
        
        $hash = crc32($name);
        $colorIndex = abs($hash) % count($colors);
        $bgColor = $colors[$colorIndex];
        $textColor = '';
    }
    
    // ОПРЕДЕЛЯЕМ ОНЛАЙН СТАТУС
    $onlineStatus = false;
    if ($showStatus) {
        // ПРИОРИТЕТ 1: Если isOnline передан явно - используем его (самый быстрый путь)
        if ($isOnline !== null) {
            $onlineStatus = $isOnline;
        } 
        // ПРИОРИТЕТ 2: Если передан userId, но нет isOnline - идем в кеш/БД
        elseif ($userId) {
            $onlineStatus = \Illuminate\Support\Facades\Cache::remember("user_online_{$userId}", 60, function () use ($userId) {
                return \DB::table('sessions')
                    ->where('user_id', $userId)
                    ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
                    ->exists();
            });
        }
    }
    
    $finalStatus = $onlineStatus;
@endphp

<div class="relative shrink-0 {{ $sizeClass }} {{ $class }}">
    <!-- Аватар -->
    <div class="relative rounded-full overflow-hidden size-full border-border border">
        @if($src)
            <img 
                src="{{ $src }}" 
                alt="{{ $name ?? 'Avatar' }}" 
                class="size-full object-cover"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
            />
            <div 
                class="{{ $bgColor }} {{ $textColor }} hidden size-full items-center justify-center font-semibold"
                style="display: none;"
            >
                {{ $initials }}
            </div>
        @else
            <div class="{{ $bgColor }} {{ $textColor }} flex size-full items-center justify-center font-semibold">
                {{ $initials }}
            </div>
        @endif
    </div>

    <!-- Статус онлайн -->
    @if($showStatus)
        <div 
            class="absolute bottom-0 right-0 {{ $statusSize }} rounded-full {{ $finalStatus ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600' }} translate-x-[15%] translate-y-[15%]"
            title="{{ $finalStatus ? 'Онлайн' : 'Офлайн' }}"
        ></div>
    @endif
</div>