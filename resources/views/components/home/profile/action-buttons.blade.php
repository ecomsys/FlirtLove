@props([
    'user',
    'isFavorited' => false,
    'isBlocked' => false,
    'isReported' => false
])

<div 
    x-data="{ 
        isAuth: {{ auth()->check() ? 'true' : 'false' }},
        isFavorited: {{ $isFavorited ? 'true' : 'false' }},
        isBlocked: {{ $isBlocked ? 'true' : 'false' }},
        isReported: {{ $isReported ? 'true' : 'false' }},
        menuOpen: false,
        reportModal: false, 
        confirmModal: false, 
        loading: false,
        reportReason: '',
        reportDesc: '',
        csrf: '{{ csrf_token() }}',
        
        async sendRequest(url, body = null, onSuccess) {
            if (!this.isAuth) { 
                this.$dispatch('open-login-modal'); 
                return; 
            }
            
            this.loading = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: body ? JSON.stringify(body) : null
                });
                
                const data = await res.json();
                
                if (data.success !== false && onSuccess) onSuccess(data);
                if (data.message) {
                    this.$dispatch('show-toast', { type: data.success !== false ? 'success' : 'error', message: data.message });
                }
            } catch(e) { 
                console.error(e);
                this.$dispatch('show-toast', { type: 'error', message: 'Ошибка сети' });
            } 
            finally { 
                this.loading = false; 
            }
        },
        
        toggleFavorite() {
            this.sendRequest(`/user/{{ $user->id }}/favorite`, null, (data) => {
                this.isFavorited = data.isFavorited;
                if (this.isBlocked && this.isFavorited) this.isBlocked = false;
            });
        },
        
        toggleBlock() {
            this.sendRequest(`/user/{{ $user->id }}/block`, null, (data) => {
                this.isBlocked = data.isBlocked;
                if (this.isBlocked && this.isFavorited) this.isFavorited = false;
                this.$dispatch('blocked-changed', this.isBlocked);
                this.confirmModal = false;
            });
        },
        
        submitReport() {
            if (!this.reportReason) { 
                this.$dispatch('show-toast', { type: 'error', message: 'Выберите причину' }); 
                return; 
            }
            
            this.sendRequest(`/user/{{ $user->id }}/report`, {
                reason: this.reportReason,
                description: this.reportDesc
            }, (data) => {
                if (data.success) {
                    this.isReported = true;
                    this.reportModal = false;
                    this.reportReason = '';
                    this.reportDesc = '';
                }
            });
        },
        
        startChat() {
            this.sendRequest(`/user/{{ $user->id }}/chat`);
        }
    }"
    class="flex flex-wrap items-center gap-3 mb-6"
>
    <!-- Кнопка Написать -->
    <x-ui.button variant="default" class="flex-1 max-w-[16rem]" x-bind:disabled="loading" @click="startChat()">
        <span class="flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
            Написать
        </span>
    </x-ui.button>

    <!-- Кнопка Избранное -->
    <button 
        @click="toggleFavorite()" 
        x-bind:disabled="loading || isBlocked" 
        class="flex-1 max-w-[16rem] h-9 px-4 rounded-md text-sm font-medium border border-border bg-background text-foreground hover:bg-accent transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
    >
        <svg class="w-4 h-4 transition-all duration-200" 
             x-bind:class="isFavorited ? 'text-primary' : 'text-foreground'" 
             x-bind:fill="isFavorited ? 'currentColor' : 'none'" 
             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
        </svg>
        <span x-text="isFavorited ? 'В избранном' : 'В избранное'">В избранное</span>
    </button>

    <!-- Триггер меню -->
    <div class="relative" @mouseenter="menuOpen = true" @mouseleave="menuOpen = false">
        <button class="h-9 px-3 border border-border rounded-md hover:bg-accent transition-colors flex items-center justify-center">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="5" r="2.5"/>
                <circle cx="12" cy="12" r="2.5"/>
                <circle cx="12" cy="19" r="2.5"/>
            </svg>
        </button>
        
        <div x-show="menuOpen" x-cloak style="display: none;"
            class="absolute right-0 top-full pt-3 w-52 z-50"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
        >
            <div class="relative bg-popover border border-border rounded-md shadow-lg overflow-visible">
                <div class="absolute right-4 -top-2 w-4 h-4 rotate-45 bg-popover border-l border-t border-border"></div>
                
                <div class="relative p-1.5">
                    <button 
                        @click="if(!isReported) { reportModal = true; menuOpen = false }" 
                        x-bind:disabled="isReported"
                        class="w-full text-left px-3 py-2 text-sm rounded-md flex items-center gap-2 transition-colors"
                        x-bind:class="isReported ? 'text-muted-foreground cursor-not-allowed opacity-60' : 'hover:bg-accent'"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                        <span x-text="isReported ? 'Жалоба отправлена' : 'Пожаловаться'"></span>
                    </button>
                    
                    <button 
                        @click="confirmModal = true; menuOpen = false" 
                        class="w-full text-left px-3 py-2 text-sm hover:bg-accent transition-colors rounded-md flex items-center gap-2" 
                        x-bind:class="isBlocked ? 'text-green-600' : 'text-destructive'"
                    >
                        <template x-if="!isBlocked">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728" /></svg>
                        </template>
                        <template x-if="isBlocked">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        </template>
                        <span x-text="isBlocked ? 'Разблокировать' : 'Заблокировать'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ТЕЛЕПОРТ МОДАЛОК В <body> -->
    <template x-teleport="body">
        <x-ui-2.modal-confirm x-show="confirmModal" @close="confirmModal = false" @confirm="toggleBlock()" />
    </template>

    <template x-teleport="body">
        <x-ui-2.modal-report :user="$user" x-show="reportModal" />
    </template>
</div>