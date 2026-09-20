<x-layouts.inapp-page>

    <!-- БЛОК 1: ПОНРАВИВШИЕСЯ ЛЮДИ (Широкая плашка + Аватарки на границе) -->
    <div class="relative w-full -mx-4 px-4 md:mx-0 md:px-0">
        <!-- Плашка (30% прозрачность основного цвета) -->
        <div
            class="w-full bg-orange-500/30 dark:bg-orange-500/20 pt-6 pb-16 flex flex-col items-center gap-6 border border-orange-500/20">
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
            <h1 class="text-2xl font-bold text-foreground">Свобода общения и новые свидания с <span class="text-amber-500">Премиум</span>-доступом</h1>
        </div>

        <!-- БЛОК 2: ПРЕИМУЩЕСТВА ПРЕМИУМА (Карточки с иконками на границе) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-10">
            @foreach ($benefits as $benefit)
                <div
                    class="relative bg-orange-500/10 dark:bg-orange-500/5 border border-orange-500/20 rounded-lg px-6 pt-8 pb-3 flex flex-col items-center text-center gap-3 shadow-sm">
                    <!-- Иконка (протыкает верхнюю границу) -->
                    <div
                        class="absolute -top-6 left-1/2 -translate-x-1/2 w-13 h-13 rounded-full bg-orange-500 text-white flex items-center justify-center shadow-lg border-4 border-background">
                        @switch($benefit['icon'])
                            @case('message')
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                            @break

                            @case('heart')
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                </svg>
                            @break

                            @case('users')
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            @break

                            @case('star')
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                </svg>
                            @break

                            @case('eye')
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            @break

                            @case('ban')
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
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
            <button onclick="window.dispatchEvent(new CustomEvent('open-premium-modal'))"
                class="w-full max-w-sm flex items-center justify-center gap-2 py-4 px-6 bg-gradient-to-r from-orange-500 to-amber-500 text-white font-semibold rounded-lg shadow-lg hover:from-orange-600 hover:to-amber-600 transition-all text-lg">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                Включить Премиум
            </button>
        </div>

    </div>
</x-layouts.inapp-page>
