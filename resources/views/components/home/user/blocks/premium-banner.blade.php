<!-- Баннер Премиума (Если авторизован и НЕТ премиума) -->

<a href="/premium"
    class="block relative mb-6 overflow-hidden rounded-2xl p-5 shadow-sm border border-green-200 dark:border-green-800/50 bg-gradient-to-br from-green-50 to-emerald-100 dark:from-green-950/40 dark:to-emerald-950/20 transition-colors">

    <!-- Декоративный фоновый glow (Свечение слева) -->
    <div
        class="absolute top-0 left-0 w-40 h-40 bg-green-500/10 dark:bg-green-400/10 rounded-full blur-3xl pointer-events-none">
    </div>

    <!-- Волны и двойная стрелка справа -->
    <div class="absolute right-6 top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
        <div class="w-24 h-24 rounded-full border-2 border-green-500/10 dark:border-green-400/10 -mr-8"></div>
        <div class="w-24 h-24 rounded-full border-2 border-green-500/10 dark:border-green-400/10 -mr-8"></div>
        <div class="w-24 h-24 rounded-full border-2 border-green-500/10 dark:border-green-400/10 -mr-8"></div>

        <!-- Двойная стрелка (Указатель) -->
        <div class="w-12 h-12 flex items-center justify-center text-green-600 dark:text-green-400">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
            </svg>
        </div>
    </div>

    <!-- Контент баннера -->
    <div class="relative z-10 flex items-center gap-4">

        <!-- Левая часть: Аватарки и Корона -->
        <div class="flex items-center -space-x-3 shrink-0">
            <img src="https://i.pravatar.cc/100?img=47"
                class="w-12 h-12 rounded-full border-2 border-white dark:border-slate-900 object-cover shadow-md"
                alt="Девушка 1">
            <img src="https://i.pravatar.cc/100?img=45"
                class="w-12 h-12 rounded-full border-2 border-white dark:border-slate-900 object-cover shadow-md"
                alt="Девушка 2">
            <!-- Золотая корона -->
            <div
                class="flex w-12 h-12 items-center justify-center rounded-full border-2 border-white dark:border-slate-900 bg-gradient-to-br from-yellow-400 to-amber-600 shadow-md shadow-amber-500/30 z-10">
                <svg class="w-6 h-6 text-slate-900" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z" />
                </svg>
            </div>
        </div>

        <!-- Центр: Текст -->
        <div class="flex-1 text-left pl-2 pr-4">
            <h4 class="font-bold text-green-900 dark:text-green-100 text-base">
                Хочешь больше возможностей?
            </h4>
            <p class="text-green-700/80 dark:text-green-300/80 text-xs mt-1">Оформи Premium и получи свободу общения !
            </p>
        </div>
    </div>
</a>
