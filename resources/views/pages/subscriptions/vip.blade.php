<x-layouts.inapp-page>

    <!-- БЛОК 1: ПОНРАВИВШИЕСЯ ЛЮДИ (Широкая плашка + Аватарки на границе) -->
    <div class="relative w-full -mx-4 px-4 md:mx-0 md:px-0">
        <!-- Плашка (30% прозрачность синего) -->
        <div
            class="w-full bg-blue-500/30 dark:bg-blue-500/20 pt-6 pb-16 flex flex-col items-center gap-6 border border-blue-500/20">
            <!-- Текст с сердечком -->
            <div
                class="inline-flex items-center gap-2 bg-card/80 backdrop-blur-sm text-red-600 dark:text-red-400 border border-red-500/30 rounded-full px-5 py-2 shadow-sm">
                <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.653 16.915V-.001h-.016v16.915h.016zm0 0l-.016-.016v.016h.016zm-.016 0v-.016h-.016v.016h.016zm-1.33-16.915h-.016v16.915h.016V-.001zm0 0v-.001h.016v.001h-.016zm.016 16.915V-.001h-.016v16.915h.016zM8.307 0v.001h.016V0h-.016zm.016 0v.001h.016V0h-.016zM7.81 0v.001h.016V0H7.81z" />
                    <path d="M3.5 9.5a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-3z" />
                </svg>
                <span class="font-semibold text-sm">Ты понравился {{ $likesCount }} девушкам. Они не против пообщаться</span>
            </div>
        </div>

        <!-- Ряд аватарок (лежат на нижней границе плашки) -->
        <div class="flex justify-center -mt-12">
            <div class="flex items-center justify-center -space-x-6">
                @foreach ($avatars as $avatar)
                    <img src="{{ $avatar }}"
                        class="w-24 h-24 rounded-full border-4 border-card object-cover shadow-md" alt="Avatar">
                @endforeach

                <!-- Пульсирующий красный кружок с числом -->
                <div class="relative w-24 h-24 flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full bg-red-500/30 animate-ping"></div>
                    <div
                        class="relative w-24 h-24 rounded-full bg-red-500 text-white flex items-center justify-center font-bold text-2xl border-4 border-card shadow-md">
                        +{{ $likesCount }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-6 space-y-8">

        <div class="text-center">
            <h1 class="text-2xl font-bold text-foreground">Новые возможности и ещё больше знакомств с <span class="text-blue-500">VIP</span>-статусом</h1>
        </div>

        <!-- БЛОК 2: ПРЕИМУЩЕСТВА VIP (Карточки с иконками на границе) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-10">
            @foreach ($benefits as $benefit)
                <div
                    class="relative bg-blue-500/10 dark:bg-blue-500/5 border border-blue-500/20 rounded-lg px-6 pt-8 pb-3 flex flex-col items-center text-center gap-3 shadow-sm">
                    <!-- Иконка (протыкает верхнюю границу) -->
                    <div
                        class="absolute -top-6 left-1/2 -translate-x-1/2 w-13 h-13 rounded-full bg-blue-500 text-white flex items-center justify-center shadow-lg border-4 border-background">
                        @switch($benefit['icon'])
                            @case('top')
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.518l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941" />
                                </svg>
                            @break

                            @case('photo')
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                            @break

                            @case('message')
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                </svg>
                            @break
                        @endswitch
                    </div>

                    <!-- Текст преимущества -->
                    <p class="text-sm text-foreground/90 font-medium leading-snug">{{ $benefit['text'] }}</p>
                </div>
            @endforeach
        </div>

        <!-- КНОПКА ПОКУПКИ -->
        <div class="flex justify-center pb-4">
            <button onclick="window.dispatchEvent(new CustomEvent('open-vip-modal'))"
                class="w-full max-w-sm flex items-center justify-center gap-2 py-4 px-6 bg-gradient-to-r from-blue-500 to-indigo-500 text-white font-semibold rounded-lg shadow-lg hover:from-blue-600 hover:to-indigo-600 transition-all text-lg">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                Включить VIP-статус
            </button>
        </div>

    </div>
</x-layouts.inapp-page>