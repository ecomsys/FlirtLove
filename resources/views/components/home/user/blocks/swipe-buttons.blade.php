@props(['user'])

@php
    $existingSwipe = null;
    if (auth()->check()) {
        $existingSwipe = \App\Models\Swipe::where('user_id', auth()->id())
            ->where('target_user_id', $user->id)
            ->whereNull('rewinded_at')
            ->first();
    }
@endphp

<div x-data="{
    loading: false,
    currentSwipe: {{ $existingSwipe ? "'{$existingSwipe->type}'" : 'null' }},
    csrf: '{{ csrf_token() }}',

    async swipe(type) {
        if (this.loading || this.currentSwipe === type) return;
        this.loading = true;

        try {
            const res = await fetch(`/user/{{ $user->slug }}/swipe`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrf,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ type: type })
            });

            const data = await res.json();

            if (data.success) {
                this.currentSwipe = type;

                if (data.is_match) {
                    this.$dispatch('show-toast', { type: 'success', message: 'Поздравляем! У вас мэтч!' });
                } else if (data.unmatched) {
                    this.$dispatch('show-toast', { type: 'info', message: data.message });
                } else {
                    this.$dispatch('show-toast', { type: 'success', message: type === 'like' ? 'Лайк отправлен' : 'Вы отклонили анкету' });
                }
            } else if (data.message) {
                this.$dispatch('show-toast', { type: 'error', message: data.message });
            }
        } catch (e) {
            console.error(e);
            this.$dispatch('show-toast', { type: 'error', message: 'Ошибка сети' });
        } finally {
            this.loading = false;
        }
    }
}" class="flex gap-3">
    <!-- Кнопка Дизлайк с тултипом -->
    <x-web-ui.tooltip text="Не нравится">
        <button @click="swipe('dislike')" x-bind:disabled="loading"
            class="w-13 h-13 rounded-full flex items-center justify-center transition-all duration-200 shadow-sm disabled:opacity-50"
            x-bind:class="currentSwipe === 'dislike'
                ?
                'bg-red-500 text-white shadow-md' :
                'bg-red-500/5 text-red-400 hover:bg-red-500/15 hover:text-red-500 hover:scale-105'">
            <template x-if="!loading">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </template>
            <template x-if="loading && currentSwipe === 'dislike'">
                <svg class="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </template>
        </button>
    </x-web-ui.tooltip>

    <!-- Кнопка Лайк с тултипом -->
    <x-web-ui.tooltip text="Нравится">
        <button @click="swipe('like')" x-bind:disabled="loading"
            class="w-13 h-13 rounded-full flex items-center justify-center transition-all duration-200 shadow-sm disabled:opacity-50"
            x-bind:class="currentSwipe === 'like'
                ?
                'bg-green-500 text-red-500 shadow-md' :
                'bg-green-500/5 text-green-400 hover:bg-green-500/15 hover:text-green-500 hover:scale-105'">
            <template x-if="!loading">
            <!-- x-bind:fill делает иконку залитой, когда она выбрана -->
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                x-bind:fill="currentSwipe === 'like' ? 'currentColor' : 'none'">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
            </template>
            <template x-if="loading && currentSwipe === 'like'">
                <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </template>
        </button>
    </x-web-ui.tooltip>
</div>
