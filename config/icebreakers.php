<?php

return [
    // Быстрые сообщения (Icebreakers) для первого сообщения в чате
    'first_message' => [
        'Привет! Как настроение? 😊',
        'Классные фото! Откуда это? 📸',
        'Привет! Давай познакомимся? 🚀',
        'У нас мэтч! Что будем делать? 😄',
        'Привет! Глянул твою анкету, очень понравилось. Как прошел день?',
    ],

    // Быстрые ответы (кнопки под полем ввода, когда чат уже открыт)
    'quick_replies' => [
        'Спасибо! 🙏',
        'Согласен(на) 👍',
        'Давай созвонимся? 📞',
        'Ха-ха, отличная шутка 😂',
        'Расскажи поподробнее 🤔',
    ],
];

// Как это использовать ?

// {{-- ПРОВЕРЯЕМ: ВКЛЮЧЕНА ЛИ НАСТРОЙКА ПОКАЗА КНОПОК --}}
// @if(auth()->user()->preferences && auth()->user()->preferences->show_quick_replies)
    
//     {{-- Если чат пустой, предлагаем начать разговор --}}
//     @if($chat->messages->isEmpty())
//         <div class="flex flex-wrap gap-2 mb-2">
//             @foreach(config('icebreakers.first_message') as $phrase)
//                 <button wire:click="sendMessage('{{ $phrase }}')" 
//                         class="px-3 py-1 text-xs border rounded-full hover:bg-accent transition-colors">
//                     {{ $phrase }}
//                 </button>
//             @endforeach
//         </div>

//     {{-- Если переписка только началась (до 10 сообщений), подкидываем быстрые ответы --}}
//     @elseif($chat->messages->count() <= 10)
//         <div class="flex flex-wrap gap-2 mb-2">
//             @foreach(config('icebreakers.quick_replies') as $phrase)
//                 <button wire:click="sendMessage('{{ $phrase }}')" 
//                         class="px-3 py-1 text-xs border rounded-full hover:bg-accent transition-colors">
//                     {{ $phrase }}
//                 </button>
//             @endforeach
//         </div>
//     @endif

// @endif

// {{-- Само поле ввода и кнопка Отправить --}}
// <div class="flex items-center gap-2">
//     <input type="text" wire:model="messageBody" placeholder="Написать сообщение...">
//     <button wire:click="sendMessage(auth()->user()->id)">Отправить</button>
// </div>