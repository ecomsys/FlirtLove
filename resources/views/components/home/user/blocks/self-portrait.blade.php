@props(['user'])

@php
    $p = $user->profile;
    if (!$p || empty($p->self_portrait)) return;

    $text = is_array($p->self_portrait) ? implode(PHP_EOL, $p->self_portrait) : $p->self_portrait;
    $text = trim($text);

    if (empty($text)) return;

    $isLong = mb_strlen($text) > 150 || str_word_count($text) > 20;
@endphp

<div class="mb-6 border-t border-border pt-4">
    <h3 class="text-base font-medium text-accent-foreground mb-2">Автопортрет</h3>
    
    @if ($isLong)      
        <div x-data="{ open: false }">
            <!-- Текст. Если закрыто - обрезаем до 2 строк -->
            <p 
                x-bind:class="!open ? 'line-clamp-2' : ''"
                class="text-base text-muted-foreground italic"
            >
                {{ $text }}
            </p>
            
            <!-- Явная кнопка для развертывания/свертывания -->
            <button 
                @click="open = !open" 
                class="ml-auto text-blue-500 dark:text-blue-400 text-xs font-medium hover:underline mt-2 flex items-center gap-1"
            >
                <!-- Меняем текст в зависимости от состояния -->
                <span x-text="open ? 'Свернуть' : 'Читать далее'"></span>
                
                <!-- Меняем иконку стрелочки -->
                <svg x-show="!open" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
                <svg x-show="open" x-cloak class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                </svg>
            </button>
        </div>
    @else       
        <p class="text-base text-muted-foreground italic">
            {{ $text }}
        </p>
    @endif
</div>