@props([
    'searchConfig' => [],
    'advFilters' => [],
    'defaultSearchGender' => 'any',
    'autoOpenLogin' => false,
    'isSearchPage' => false,
])

<div x-data="userFeed({
    isAuth: {{ auth()->check() ? 'true' : 'false' }},
    autoOpenLogin: {{ $autoOpenLogin ? 'true' : 'false' }},
    searchConfig: @js($searchConfig),
    advFilters: @js($advFilters),
    defaultGender: @js($defaultSearchGender),
    isSearchPage: {{ $isSearchPage ? 'true' : 'false' }}
})" x-init="init()" class="space-y-6">

   
    <!-- 1. Единая панель поиска (Плашка + Тело) -->
    <div class="bg-card border border-border rounded-xl mb-6 shadow-sm">
        
        <!-- Плашка-заголовок (Видна только на странице поиска для авторизованных) -->
        @auth
            <div x-show="isSearchPage" @click="showSearchPanel = !showSearchPanel" class="p-4 flex items-center justify-between cursor-pointer hover:bg-accent/50 transition-colors">
                <span class="text-sm font-medium text-foreground" x-text="showSearchPanel ? 'Фильтры поиска' : 'Найти людей'"></span>
                <div class="flex items-center gap-2 text-sm font-medium text-primary">
                    <span x-text="showSearchPanel ? 'Свернуть' : 'Развёрнуть поиск'"></span>
                    <svg class="w-4 h-4 transition-transform duration-300" :class="showSearchPanel ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </div>
            </div>
        @endauth

        <!-- Тело поиска (Плавно сворачивается/разворачивается) -->
        <div x-show="showSearchPanel" x-collapse.duration.500ms>
            <div class="p-6" :class="isSearchPage ? 'border-t border-border' : 'border-t-0'">
                
                <!-- Обычный поиск (2 колонки) -->
                <div class="grid md:grid-cols-2 gap-8 md:gap-6">
                    <!-- ЛЕВАЯ КОЛОНКА -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <label class="text-sm font-medium text-muted-foreground whitespace-nowrap">Кого я ищу</label>
                            @auth
                                <div class="inline-flex items-center h-10 px-3 border border-border rounded-md bg-muted/30 text-sm font-medium">
                                    <span x-text="filters.gender === 'male' ? 'Парня' : 'Девушку'"></span>
                                </div>
                            @else
                                <x-ui.select x-model="filters.gender" x-modelable="value" class="w-full max-w-[14rem]" :options="['any' => 'Всех', 'male' => 'Парней', 'female' => 'Девушек']" placeholder="Выберите пол" />
                            @endauth
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <label class="text-sm font-medium text-muted-foreground whitespace-nowrap">Регион</label>
                            <x-ui.select x-model="filters.city_id" x-modelable="value" class="w-full max-w-[14rem]" :options="['' => 'Любой']" placeholder="Любой" />
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <label class="text-sm font-medium text-muted-foreground whitespace-nowrap">Цель отношений</label>
                            <x-ui.select x-model="filters.dating_goal" x-modelable="value" class="w-full max-w-[14rem]" :options="['any' => 'Любая', 'friends' => 'Дружба', 'romantic' => 'Романтика', 'family' => 'Семья', 'casual' => 'Свободные отношения', 'travel' => 'Путешествия']" placeholder="Любая" />
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <label class="text-sm font-medium text-muted-foreground whitespace-nowrap">Активность</label>
                            <x-ui.select x-model="filters.activity" x-modelable="value" class="w-full max-w-[14rem]" :options="['' => 'Не имеет значения', 'online' => 'Сейчас онлайн', 'recently' => 'Недавно активные']" placeholder="Не имеет значения" />
                        </div>
                    </div>
                    <!-- ПРАВАЯ КОЛОНКА -->
                    <div class="space-y-6 md:border-l border-border md:pl-6">
                        <div class="space-y-2 max-w-[16rem]">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium text-muted-foreground whitespace-nowrap">Возраст</label>
                                <span class="text-sm font-medium text-muted-foreground" x-text="filters.age[0] + ' - ' + filters.age[1]"></span>
                            </div>
                            <div class="pt-2">
                                <x-ui.slider range x-model="filters.age" @change="filters.age_from = filters.age[0]; filters.age_to = filters.age[1];" min="18" max="99" :value="[18, 50]" aria-label="Age" />
                            </div>
                        </div>
                        <div class="space-y-3 pt-2 flex flex-col items-start">
                            <div class="flex items-center gap-2">
                                <x-ui.checkbox id="is_new" x-model="filters.is_new" />
                                <x-ui.label for="is_new">Новые пользователи</x-ui.label>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ui.checkbox id="verified_only" x-model="filters.verified_only" />
                                <x-ui.label for="verified_only">Проверенные фото</x-ui.label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- РАСШИРЕННЫЙ ПОИСК (Выезжает ровно над кнопками) -->
                @auth
                <div x-show="showAdvanced" x-collapse.duration.500ms>
                    <div class="space-y-6 mt-6 pt-6 border-t border-border">
                        <div>
                            <label class="text-sm font-medium text-muted-foreground block mb-1.5">Интересы (через запятую)</label>
                            <x-ui.input type="text" x-model="filters.interests_string" placeholder="Например: кино, музыка, спорт" />
                        </div>
                        <div class="grid md:grid-cols-3 gap-6">
                            <!-- Колонка 1 -->
                            <div class="space-y-3">   
                                <x-web-ui.select-2 x-model="filters.adv1" :options="$advFilters" exclude-values="[filters.adv2, filters.adv3]" placeholder="Дополнительное поле" />                             
                                <template x-if="filters.adv1 === 'weight'">
                                    <div class="p-4 border border-border rounded-lg bg-muted/20 space-y-2">
                                        <div class="flex items-center justify-between"><label class="text-sm font-medium text-muted-foreground">Вес</label><span class="text-sm font-medium text-muted-foreground" x-text="filters.weight[0] + ' - ' + filters.weight[1] + ' кг'"></span></div>
                                        <div class="pt-2"><x-ui.slider range x-model="filters.weight" min="30" max="200" :value="[45, 85]" aria-label="Weight" /></div>
                                    </div>
                                </template>
                                <template x-if="filters.adv1 === 'height'">
                                    <div class="p-4 border border-border rounded-lg bg-muted/20 space-y-2">
                                        <div class="flex items-center justify-between"><label class="text-sm font-medium text-muted-foreground">Рост</label><span class="text-sm font-medium text-muted-foreground" x-text="filters.height[0] + ' - ' + filters.height[1] + ' см'"></span></div>
                                        <div class="pt-2"><x-ui.slider range x-model="filters.height" min="130" max="250" :value="[150, 185]" aria-label="Height" /></div>
                                    </div>
                                </template>
                                <template x-if="filters.adv1 !== 'none' && filters.adv1 !== 'weight' && filters.adv1 !== 'height' && searchConfig && searchConfig[filters.adv1]">
                                    <div class="flex flex-col gap-2 little-scroll overflow-y-auto p-2 border border-border rounded-lg">
                                        <template x-for="(label, val) in searchConfig[filters.adv1]" :key="filters.adv1 + '-' + val">
                                            <x-web-ui.checkbox-2 checked-expr="filters.adv1_values.includes(String(val))" click-expr="toggleValue(filters.adv1_values, val)"><span x-text="label"></span></x-web-ui.checkbox-2>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <!-- Колонка 2 -->
                            <div class="space-y-3">
                                <x-web-ui.select-2 x-model="filters.adv2" :options="$advFilters" exclude-values="[filters.adv1, filters.adv3]" placeholder="Дополнительное поле" />
                                <template x-if="filters.adv2 === 'weight'">
                                    <div class="p-4 border border-border rounded-lg bg-muted/20 space-y-2">
                                        <div class="flex items-center justify-between"><label class="text-sm font-medium text-muted-foreground">Вес</label><span class="text-sm font-medium text-muted-foreground" x-text="filters.weight[0] + ' - ' + filters.weight[1] + ' кг'"></span></div>
                                        <div class="pt-2"><x-ui.slider range x-model="filters.weight" min="30" max="200" :value="[45, 85]" aria-label="Weight" /></div>
                                    </div>
                                </template>
                                <template x-if="filters.adv2 === 'height'">
                                    <div class="p-4 border border-border rounded-lg bg-muted/20 space-y-2">
                                        <div class="flex items-center justify-between"><label class="text-sm font-medium text-muted-foreground">Рост</label><span class="text-sm font-medium text-muted-foreground" x-text="filters.height[0] + ' - ' + filters.height[1] + ' см'"></span></div>
                                        <div class="pt-2"><x-ui.slider range x-model="filters.height" min="130" max="250" :value="[150, 185]" aria-label="Height" /></div>
                                    </div>
                                </template>
                                <template x-if="filters.adv2 !== 'none' && filters.adv2 !== 'weight' && filters.adv2 !== 'height' && searchConfig && searchConfig[filters.adv2]">
                                    <div class="flex flex-col gap-2 overflow-y-auto little-scroll p-2 border border-border rounded-lg">
                                        <template x-for="(label, val) in searchConfig[filters.adv2]" :key="filters.adv2 + '-' + val">
                                            <x-web-ui.checkbox-2 checked-expr="filters.adv2_values.includes(String(val))" click-expr="toggleValue(filters.adv2_values, val)"><span x-text="label"></span></x-web-ui.checkbox-2>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <!-- Колонка 3 -->
                            <div class="space-y-3">
                                <x-web-ui.select-2 x-model="filters.adv3" :options="$advFilters" exclude-values="[filters.adv1, filters.adv2]" placeholder="Дополнительное поле" />
                                <template x-if="filters.adv3 === 'weight'">
                                    <div class="p-4 border border-border rounded-lg bg-muted/20 space-y-2">
                                        <div class="flex items-center justify-between"><label class="text-sm font-medium text-muted-foreground">Вес</label><span class="text-sm font-medium text-muted-foreground" x-text="filters.weight[0] + ' - ' + filters.weight[1] + ' кг'"></span></div>
                                        <div class="pt-2"><x-ui.slider range x-model="filters.weight" min="30" max="200" :value="[45, 85]" aria-label="Weight" /></div>
                                    </div>
                                </template>
                                <template x-if="filters.adv3 === 'height'">
                                    <div class="p-4 border border-border rounded-lg bg-muted/20 space-y-2">
                                        <div class="flex items-center justify-between"><label class="text-sm font-medium text-muted-foreground">Рост</label><span class="text-sm font-medium text-muted-foreground" x-text="filters.height[0] + ' - ' + filters.height[1] + ' см'"></span></div>
                                        <div class="pt-2"><x-ui.slider range x-model="filters.height" min="130" max="250" :value="[150, 185]" aria-label="Height" /></div>
                                    </div>
                                </template>
                                <template x-if="filters.adv3 !== 'none' && filters.adv3 !== 'weight' && filters.adv3 !== 'height' && searchConfig && searchConfig[filters.adv3]">
                                    <div class="flex flex-col gap-2 little-scroll overflow-y-auto p-2 border border-border rounded-lg">
                                        <template x-for="(label, val) in searchConfig[filters.adv3]" :key="filters.adv3 + '-' + val">
                                            <x-web-ui.checkbox-2 checked-expr="filters.adv3_values.includes(String(val))" click-expr="toggleValue(filters.adv3_values, val)"><span x-text="label"></span></x-web-ui.checkbox-2>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                @endauth

                <!-- ЕДИНСТВЕННАЯ строка с кнопками -->
                <div class="flex justify-between items-center mt-6 pt-4 border-t border-border">
                    <x-ui.button @click="applyFilters()" variant="default" size="md" x-bind:disabled="loading">
                        <span x-show="!loading">Искать</span>
                        <svg x-show="loading" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </x-ui.button>
                    @auth
                        <button @click="showAdvanced = !showAdvanced" class="text-sm text-primary hover:underline flex items-center gap-1.5">
                            <span x-text="showAdvanced ? 'Скрыть расширенный поиск' : 'Расширенный поиск'"></span>
                            <svg class="w-4 h-4 transition-transform duration-300" :class="showAdvanced ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    @endauth
                </div>

            </div>
        </div>
    </div> <!-- Конец единой панели поиска -->
        <!-- 2. Сетка анкет -->
    <div x-ref="userGrid">
        
        <!-- Крупный спиннер (Показывается, когда идёт загрузка и карточек ещё нет) -->
        <template x-if="loading && users.length === 0">
            <div class="flex justify-center items-center py-[33dvh] text-primary">
                <x-lucide-loader-2 x-show="loading" class="w-10 h-10 animate-spin inline" />
            </div>
        </template>

        <!-- Карточки (Плавно появляются, когда данные загружены) -->
              
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" x-show="!loading || users.length > 0">
            <template x-for="(user, index) in users" :key="user.id">
                <div 
                    :style="'animation: fadeInUp 0.4s ease-out forwards; animation-delay: ' + Math.min((index % 9) * 75, 400) + 'ms; opacity: 0;'"
                    @click="window.location.href = '/user/' + user.slug" 
                    class="relative group rounded-sm overflow-hidden border bg-card shadow-sm hover:shadow-md transition-all cursor-pointer flex flex-col" 
                    :class="user.has_premium ? 'border-3 border-yellow-400 shadow-md shadow-yellow-400/30' : 'border-border'"
                >
                    <div class="aspect-[5/4] bg-muted relative">
                        <template x-if="user.photo"><img :src="user.photo" :alt="user.name" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"></template>
                        <template x-if="!user.photo"><div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-muted to-muted/50"><svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-muted-foreground/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div></template>
                        <div class="absolute top-2 left-2 bg-black/60 text-white text-xs px-2 py-1 rounded-md flex items-center gap-1 backdrop-blur-sm z-10">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" /></svg>
                            <span x-text="user.photos_count"></span>
                        </div>
                    </div>
                    <div class="p-3 space-y-1.5 text-sm flex-1 flex flex-col">
                        <div class="font-semibold text-foreground flex items-baseline gap-1"><span x-text="user.name"></span>, <span x-text="user.age"></span><span class="font-normal text-muted-foreground text-xs" x-text="'(' + user.zodiac + ')'"></span></div>
                        <div class="text-muted-foreground text-xs" x-text="user.city"></div>
                        <a @click.stop href="#" class="flex items-center gap-1 text-primary text-xs hover:underline w-fit mt-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg><span x-text="user.distance ? 'В ' + user.distance + ' от вас' : 'Геолокация не указана'"></span></a>
                        <div class="flex items-center gap-1.5 text-xs pt-1"><span class="w-2 h-2 rounded-full" :class="user.is_online ? 'bg-green-500' : 'bg-gray-400'"></span><span class="font-medium" :class="user.is_online ? 'text-green-600' : 'text-muted-foreground'" x-text="user.status_text"></span></div>
                        <button @click.stop="$dispatch('open-login-modal')" class="mt-auto pt-2 w-full bg-primary/10 text-primary hover:bg-primary/20 font-medium py-2 rounded-sm transition-colors flex items-center justify-center gap-1.5 text-xs"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>Написать</button>
                    </div>
                </div>
            </template>
            
            <!-- Пустой результат -->
            <template x-if="!loading && users.length === 0">
                <div class="col-span-full text-center py-16 text-muted-foreground">
                    <p>По вашим критериям никого не найдено. Попробуйте изменить фильтры.</p>
                </div>
            </template>
        </div>
    </div>

    <!-- 3. Логика "Показать еще" и Бесконечный скролл -->
    <div class="pt-4 flex flex-col items-center justify-center gap-4">
        <div x-show="!isSearchPage && showLoadMoreButton && currentPage < lastPage && users.length > 0" x-cloak>
            <button @click="enableInfiniteScroll()" :disabled="loading"
                class="border border-primary text-primary px-6 py-2 rounded-lg font-medium hover:bg-muted transition-colors disabled:opacity-50">
                <span x-show="!loading">Найти еще людей</span><span x-show="loading">Ищем...</span>
            </button>
        </div>
        <div x-show="!showLoadMoreButton && currentPage < lastPage" x-cloak>
            <x-lucide-loader-2 x-show="loading" class="w-8 h-8 text-primary animate-spin inline" />
            <div x-ref="scrollSentinel" class="h-1"></div>
        </div>
    </div>

    <!-- 4. БЛОК ИНФОРМАЦИИ ТОЛЬКО ДЛЯ ГОСТЯ -->
    @guest
        <div class="mt-8 bg-card border border-border rounded-xl p-8 text-center space-y-4">
            <h3 class="text-2xl font-bold">Добро пожаловать на FlirtLove! ❤️</h3>
            <p class="text-muted-foreground max-w-2xl mx-auto">Наш сайт знакомств поможет вам найти серьезные отношения,
                друзей или просто интересных собеседников. Регистрируйтесь бесплатно, чтобы получить доступ к чатам, лайкам
                и полному просмотру анкет.</p>
            <div class="flex flex-wrap justify-center gap-4 pt-2">
                <a href="/register"
                    class="bg-primary text-primary-foreground px-6 py-3 rounded-lg font-medium hover:bg-primary/90 transition-colors">Создать
                    аккаунт</a>
                <button @click="$dispatch('open-login-modal')"
                    class="border border-border text-foreground px-6 py-3 rounded-lg font-medium hover:bg-muted transition-colors">Войти</button>
            </div>
            <div class="flex flex-wrap justify-center gap-6 pt-6 text-xs text-muted-foreground">
                <a href="#" class="hover:text-primary">Правила сайта</a><a href="#"
                    class="hover:text-primary">Политика конфиденциальности</a><a href="#"
                    class="hover:text-primary">Поддержка</a>
            </div>
        </div>
    @endguest
</div>

<!-- === ALPINE SCRIPT === -->
<script>
    function userFeed({
        isAuth,
        autoOpenLogin,
        searchConfig,
        advFilters,
        defaultGender,
        isSearchPage
    }) {
        return {
            isAuth: isAuth,
            isSearchPage: isSearchPage,
            showSearchPanel: !isAuth ? true : !isSearchPage,
            showAdvanced: isAuth && !isSearchPage,
            users: [],
            loading: false,
            total: 0,
            currentPage: 1,           
            lastPage: 1,
            showLoadMoreButton: true,
            observer: null,
            searchConfig: searchConfig,
            advFilters: advFilters,

            filters: {
                gender: defaultGender,
                city_id: '',
                dating_goal: 'any',
                activity: '',
                age: [18, 50],
                age_from: 18,
                age_to: 50,
                is_new: false,
                verified_only: false,
                interests_string: '',
                adv1: 'none',
                adv1_values: [],
                adv2: 'none',
                adv2_values: [],
                adv3: 'none',
                adv3_values: [],
                weight: [45, 85],
                height: [150, 185],
            },

            get adv1_slider() {
                return this.filters[this.filters.adv1];
            },
            set adv1_slider(val) {
                this.filters[this.filters.adv1] = val;
            },
            get adv2_slider() {
                return this.filters[this.filters.adv2];
            },
            set adv2_slider(val) {
                this.filters[this.filters.adv2] = val;
            },
            get adv3_slider() {
                return this.filters[this.filters.adv3];
            },
            set adv3_slider(val) {
                this.filters[this.filters.adv3] = val;
            },

                       init() {
                if (autoOpenLogin) {
                    this.$dispatch('open-login-modal');
                }
                this.fetchUsers();
                
                // ВАЖНО: Если страница поиска — сразу выключаем кнопку и включаем бесконечный скролл
                if (this.isSearchPage) {
                    this.showLoadMoreButton = false;
                    this.$nextTick(() => {
                        if (this.$refs.scrollSentinel) {
                            this.observer = new IntersectionObserver((entries) => {
                                if (entries[0].isIntersecting && !this.loading && this.currentPage < this.lastPage) {
                                    this.loadMore();
                                }
                            }, { rootMargin: '200px' });
                            this.observer.observe(this.$refs.scrollSentinel);
                        }
                    });
                }
                
                this.$watch('filters.adv1', () => this.filters.adv1_values = []);
                this.$watch('filters.adv2', () => this.filters.adv2_values = []);
                this.$watch('filters.adv3', () => this.filters.adv3_values = []);
            },

            getPayload() {
                let payload = {
                    gender: this.filters.gender,
                    city_id: this.filters.city_id,
                    dating_goal: this.filters.dating_goal,
                    activity: this.filters.activity,
                    age_from: this.filters.age[0],
                    age_to: this.filters.age[1],
                    is_new: this.filters.is_new,
                    verified_only: this.filters.verified_only,
                    interests: this.filters.interests_string ? this.filters.interests_string.split(',').map(s => s
                            .trim()).filter(Boolean).map(s => s.replace(/[^a-zа-яё0-9\s-]/giu, '')).filter(
                        Boolean) : [],
                };
                ['adv1', 'adv2', 'adv3'].forEach(slot => {
                    const type = this.filters[slot];
                    if (type !== 'none') {
                        if (type === 'weight' || type === 'height') {
                            payload[type + '_from'] = this.filters[type][0];
                            payload[type + '_to'] = this.filters[type][1];
                        } else {
                            payload[type] = this.filters[slot + '_values'].map(v => parseInt(v));
                        }
                    }
                });
                return payload;
            },

            toggleValue(arr, val) {
                let index = arr.indexOf(String(val));
                if (index > -1) {
                    arr.splice(index, 1);
                } else {
                    arr.push(String(val));
                }
            },

            async fetchUsers() {
                this.loading = true;
                try {
                    const params = new URLSearchParams();
                    const payload = this.getPayload();
                    payload.page = this.currentPage;
                    Object.keys(payload).forEach(key => {
                        const val = payload[key];
                        if (Array.isArray(val) && val.length > 0) {
                            val.forEach(v => params.append(`${key}[]`, v));
                        } else if (val !== '' && val !== null && !(Array.isArray(val) && val.length ===
                            0)) {
                            params.append(key, val);
                        }
                    });
                    const response = await fetch(`/api/users/search?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const data = await response.json();
                    const newUsers = Array.isArray(data.users) ? data.users : Object.values(data.users || {});
                    if (this.currentPage === 1) {
                        this.users = newUsers;
                    } else {
                        this.users = [...this.users, ...newUsers];
                    }
                    this.total = data.total;
                    this.currentPage = data.current_page;
                    this.lastPage = data.last_page;
                } catch (error) {
                    console.error('Ошибка загрузки анкет:', error);
                } finally {
                    this.loading = false;
                }
            },

            applyFilters() {
                this.currentPage = 1;
                this.users = [];
                this.fetchUsers();
                // Скроллим к началу контейнера с карточками
                // this.$nextTick(() => {
                //     this.$refs.userGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // });
            },

            enableInfiniteScroll() {
                this.showLoadMoreButton = false;
                this.loadMore();
                this.$nextTick(() => {
                    if (this.$refs.scrollSentinel) {
                        this.observer = new IntersectionObserver((entries) => {
                            if (entries[0].isIntersecting && !this.loading && this.currentPage < this
                                .lastPage) {
                                this.loadMore();
                            }
                        }, {
                            rootMargin: '200px'
                        });
                        this.observer.observe(this.$refs.scrollSentinel);
                    }
                });
            },

            loadMore() {
                if (this.currentPage < this.lastPage) {
                    this.currentPage++;
                    this.fetchUsers();
                }
            }
        }
    }
</script>

<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>