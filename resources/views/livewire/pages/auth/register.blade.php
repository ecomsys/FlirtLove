<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Carbon\Carbon;

new #[Layout('layouts.guest')] class extends Component 
{
    public int $step = 1;

    // Шаг 1
    public string $name = '';
    public string $gender = '';
    public string $birth_day = '';
    public string $birth_month = '';
    public string $birth_year = '';

    // Шаг 2
    public string $dating_goal = '';

    // Шаг 3
    public string $city = '';
    public string $email = '';
    public string $password = '';

    public array $months = [];
    public array $days = [];
    public array $years = [];

    public function mount(): void
    {
        for ($m = 1; $m <= 12; $m++) {
            $this->months[$m] = Carbon::create()->month($m)->translatedFormat('F');
        }

        for ($i = 1; $i <= 31; $i++) {
            $this->days[$i] = $i;
        }

        for ($i = 2010; $i >= 1950; $i--) {
            $this->years[$i] = $i;
        }
    }

    public function step1Next(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_day' => ['required', 'integer', 'between:1,31'],
            'birth_month' => ['required', 'integer', 'between:1,12'],
            'birth_year' => ['required', 'integer', 'between:1950,2010'],
        ]);

        $this->step = 2;
    }

    public function step2Next(): void
    {
        $this->validate([
            'dating_goal' => ['required', 'in:friends,romantic,family,casual,travel'],
        ]);

        $this->step = 3;
    }

    public function back(): void
    {
        $this->step--;
    }

        public function register(): void
    {
        // Валидируем финальный шаг, а также перепроверяем данные из предыдущих шагов (защита от читеров)
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_day' => ['required', 'integer', 'between:1,31'],
            'birth_month' => ['required', 'integer', 'between:1,12'],
            'birth_year' => ['required', 'integer', 'between:1950,2010'],
            'dating_goal' => ['required', 'in:friends,romantic,family,casual,travel'],
            
            'city' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', Rules\Password::defaults()],
        ]);

        // 1. Создаем самого Юзера (только базовые поля таблицы users)
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        // 2. Безопасно формируем дату (Y-m-d), чтобы PostgreSQL не ругался на формат
        $birthDate = sprintf('%04d-%02d-%02d', $this->birth_year, $this->birth_month, $this->birth_day);

        // 3. Обновляем Профиль юзера (который был автоматически создан в User::booted())
        $user->profile->update([
            'gender' => $this->gender,
            'birth_date' => $birthDate,
            'dating_goal' => $this->dating_goal,
            'city' => $this->city,
        ]);

        event(new Registered($user));
        Auth::login($user);

        $this->redirect(route('photo.setup', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-md mx-auto py-4 bg-background text-foreground h-[calc(100vh-4rem)] flex flex-col justify-center">

    <!-- Прогресс-бар -->
    <div class="flex items-center justify-center mb-8 gap-2">
        <div class="h-2 w-16 rounded-full transition-colors {{ $step >= 1 ? 'bg-green-600' : 'bg-muted' }}"></div>
        <div class="h-2 w-16 rounded-full transition-colors {{ $step >= 2 ? 'bg-green-600' : 'bg-muted' }}"></div>
        <div class="h-2 w-16 rounded-full transition-colors {{ $step >= 3 ? 'bg-green-600' : 'bg-muted' }}"></div>
    </div>

    <h1 class="text-2xl font-semibold text-center mb-6">{{ __('auth.registration') }}</h1>

    @if ($step === 1)
        <!-- ЭТАП 1 -->
        <form wire:submit="step1Next" class="space-y-6">

            <!-- Имя -->
            <div class="space-y-2">
                <x-ui.label for="name" class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.name') }}
                </x-ui.label>
                <x-ui.input wire:model="name" id="name" name="name" type="text" required autofocus
                    class="w-full bg-input border-border focus-visible:ring-ring "
                    placeholder="{{ __('auth.ph_name') }}" />
                @error('name')
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <!-- Пол -->
            <div class="space-y-2">
                <x-ui.label class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.gender') }}
                </x-ui.label>
                <div class="grid grid-cols-2 gap-4">
                    <!-- Мужчина -->
                    <div wire:click="$set('gender', 'male')"
                        class="cursor-pointer p-4 border rounded-lg flex flex-col items-center gap-2 transition-all duration-200 {{ $gender === 'male' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20' }}">
                        <svg class="w-14 h-14 {{ $gender === 'male' ? 'text-primary' : 'text-muted-foreground' }}"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                            <g fill="none">
                                <path fill="#212121"
                                    d="M26.345 5.926c-.55-.22-.992-.65-1.238-1.2c-.668-1.561-2.18-2.672-3.948-2.732a4.4 4.4 0 0 0-2.583.74c-.442.3-1.022.3-1.464 0a4.37 4.37 0 0 0-2.573-.74a4.44 4.44 0 0 0-3.065 1.381c-.363.38-.844.63-1.355.75a5.24 5.24 0 0 0-2.72 1.541l-.01.01C5.836 7.317 5.001 9.468 5.011 11.74v.02c.01.08 0 6.233 0 6.233h.994l19.994.02l1-.016s.044-1.89.072-2.465c.023-.496.039-1.27.039-1.27c.02-.671.294-1.311.736-1.811a4.1 4.1 0 0 0 1.032-2.632a4.08 4.08 0 0 0-2.534-3.892" />
                                <path fill="#F4C6AD"
                                    d="M26.122 14.519L26 18.012c.54 0 .98-.466 1-1.016l.072-1.465c.01-.225.02-.507.026-.748a3.25 3.25 0 1 1-.976-.264m-21.107.224c-.001 1.175-.004 2.25-.004 2.25c.02.55.455 1 .995 1v-3.484q.12-.009.244-.009a3.25 3.25 0 1 1-1.235.243" />
                                <path fill="#FFD7C2"
                                    d="M26.046 16.684L26 18.012h.02l-.046 2.204C25.735 26.068 21.344 30 16.005 30s-9.73-3.931-9.97-9.784l-.075-2.225l.046.001v-4c-.03-.86.361-1.523.95-2.133a4.65 4.65 0 0 0 1.228-2.451a.18.18 0 0 1 .138-.15c.098-.03.186.03.216.11c.609 1.54 2.082 2.641 3.81 2.641c.442 0 .806-.37.806-.82V9.458c0-.11.069-.19.157-.21h.128a.2.2 0 0 1 .108.08a6.63 6.63 0 0 0 5.313 2.681h5.078c1.238 0 2.23 1.04 2.19 2.311l-.032.925l-.038.84z" />
                                <path fill="#990838"
                                    d="M16.002 23.174a6.5 6.5 0 0 1-3.016-.733a.328.328 0 0 0-.429.472a4.1 4.1 0 0 0 3.445 1.887a4.1 4.1 0 0 0 3.445-1.887c.18-.281-.13-.622-.43-.472a6.35 6.35 0 0 1-3.015.733" />
                                <path fill="#E5AF93"
                                    d="M15.993 22c.68 0 1.27-.345 1.63-.873c.32-.477-.02-1.127-.59-1.127h-2.07c-.57 0-.91.65-.59 1.127c.35.528.95.873 1.62.873" />
                                <path fill="#fff"
                                    d="M8.19 17.87a3.11 3.11 0 0 1 3.01-2.34c1.51 0 2.76 1.07 3.04 2.49c.06.31-.19.6-.51.6H8.79c-.4 0-.7-.37-.6-.75m15.64 0a3.11 3.11 0 0 0-3.01-2.34c-1.51 0-2.76 1.07-3.04 2.49c-.06.31.19.6.51.6h4.94c.4 0 .7-.37.6-.75" />
                                <path fill="#7D4533"
                                    d="M9.68 18.1c0-1.09.89-1.98 1.98-1.98a1.985 1.985 0 0 1 1.91 2.51H9.75c-.04-.17-.07-.35-.07-.53m12.66 0c0-1.09-.89-1.98-1.98-1.98a1.985 1.985 0 0 0-1.91 2.51h3.82c.04-.17.07-.35.07-.53" />
                                <path fill="#000"
                                    d="M11.66 16.97a1.13 1.13 0 0 1 1 1.66h-2a1.13 1.13 0 0 1 1-1.66m8.7 0a1.13 1.13 0 0 0-1 1.66h2a1.13 1.13 0 0 0-1-1.66" />
                                <path fill="#fff"
                                    d="M11.33 17.32a.35.35 0 1 1-.7 0a.35.35 0 0 1 .7 0m8.77 0a.35.35 0 1 1-.7 0a.35.35 0 0 1 .7 0" />
                                <path fill="#212121"
                                    d="M9.608 14.563c.521-.185 1.268-.326 2.255-.233a.5.5 0 1 0 .094-.996c-1.133-.106-2.026.053-2.685.287a.5.5 0 1 0 .336.942m12.57-.942c-.66-.234-1.552-.393-2.685-.287a.5.5 0 1 0 .094.996c.987-.093 1.734.048 2.255.233a.5.5 0 0 0 .336-.942" />
                            </g>
                        </svg>
                        <span
                            class="text-sm font-medium {{ $gender === 'male' ? 'text-primary' : 'text-foreground' }}">
                            {{ __('auth.male') }}
                        </span>
                    </div>
                    <!-- Женщина -->
                    <div wire:click="$set('gender', 'female')"
                        class="cursor-pointer p-4 border rounded-lg flex flex-col items-center gap-2 transition-all duration-200 {{ $gender === 'female' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20' }}">
                        <svg class="w-14 h-14 {{ $gender === 'female' ? 'text-primary' : 'text-muted-foreground' }}"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                            <title>girl-light</title>
                            <g fill="none">
                                <path fill="#212121"
                                    d="M25.5 14.5c-.243 0-.708.077-.708.077L6.054 14.53s-1.504.51-2.008 1.089a7 7 0 0 1-.017-.489v-4.07a7.06 7.06 0 0 1 5.993-6.98A2.187 2.187 0 0 1 12.199 2h5.81c5.52 0 10 4.48 10 10v3.13q0 .266-.02.53A3.24 3.24 0 0 0 25.5 14.5M8.439 21.22l-6 4.03c-.55.37-.59 1.17-.08 1.6l3.19 2.81c.72.64 1.85.34 2.18-.56l2.32-6.49zm15.19 0l6 4.03c.55.37.59 1.17.08 1.6l-3.19 2.81c-.72.64-1.85.34-2.18-.56l-2.32-6.49z" />
                                <path fill="#F4C6AD"
                                    d="M9.75 17.75a3.25 3.25 0 1 1-6.5 0a3.25 3.25 0 0 1 6.5 0m19 0a3.25 3.25 0 1 1-6.5 0a3.25 3.25 0 0 1 6.5 0" />
                                <path fill="#FFD7C2"
                                    d="M18.829 13a8.82 8.82 0 0 1-8.1-5.337a10.24 10.24 0 0 1-3.62 5.417a3.23 3.23 0 0 0-1.055 1.45q-.106.015-.21.036l.192 5.649C6.275 26.074 10.659 30 16 30c5.33 0 9.725-3.926 9.964-9.785l.192-5.649a3.3 3.3 0 0 0-1.364.011A4.86 4.86 0 0 0 21.19 13z" />
                                <path fill="#990839"
                                    d="M16.014 23.373a6.5 6.5 0 0 1-3.016-.733a.329.329 0 0 0-.43.472a4.088 4.088 0 0 0 6.89 0c.18-.281-.13-.622-.429-.472a6.35 6.35 0 0 1-3.015.733" />
                                <path fill="#E5AF93"
                                    d="M16.003 22c.67 0 1.27-.345 1.62-.873c.32-.477-.02-1.127-.59-1.127h-2.07c-.57 0-.91.65-.59 1.127c.36.528.96.873 1.63.873" />
                                <path fill="#fff"
                                    d="M8.219 18.47a3.09 3.09 0 0 1 3-2.34c1.5 0 2.76 1.07 3.05 2.49c.06.31-.19.6-.51.6h-4.94c-.4 0-.7-.37-.6-.75m15.6 0a3.09 3.09 0 0 0-3-2.34c-1.5 0-2.75 1.07-3.05 2.49c-.06.31.19.6.51.6h4.94c.4 0 .7-.37.6-.75" />
                                <path fill="#7D4533"
                                    d="M9.699 18.7c0-1.09.89-1.98 1.98-1.98s1.98.89 1.97 1.98c0 .18-.02.35-.07.52h-3.81c-.04-.16-.07-.34-.07-.52m12.65 0c0-1.09-.89-1.98-1.98-1.98c-1.1 0-1.98.89-1.97 1.98c0 .18.02.35.07.52h3.81c.04-.16.07-.34.07-.52" />
                                <path fill="#000"
                                    d="M11.679 17.57c.62 0 1.13.51 1.13 1.13c0 .19-.05.37-.13.52h-2c-.08-.15-.13-.33-.13-.52c0-.62.51-1.13 1.13-1.13m8.69 0c-.62 0-1.13.51-1.13 1.13c0 .19.04.37.13.52h2c.08-.15.13-.33.13-.52c0-.62-.51-1.13-1.13-1.13" />
                                <path fill="#fff"
                                    d="M11.349 17.92a.35.35 0 1 1-.7 0a.35.35 0 0 1 .7 0m8.75 0a.35.35 0 1 1-.7 0a.35.35 0 0 1 .7 0" />
                                <path fill="#212121"
                                    d="M9.906 15.171c.522-.185 1.268-.326 2.256-.233a.5.5 0 0 0 .094-.996c-1.133-.107-2.026.053-2.685.287a.5.5 0 0 0 .335.942m12.56-.942c-.658-.234-1.552-.394-2.684-.287a.5.5 0 1 0 .094.996c.987-.093 1.734.047 2.255.233a.5.5 0 1 0 .335-.942" />
                            </g>
                        </svg>
                        <span
                            class="text-sm font-medium {{ $gender === 'female' ? 'text-primary' : 'text-foreground' }}">
                            {{ __('auth.female') }}
                        </span>
                    </div>
                </div>
                @error('gender')
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>
            
            <!-- Дата рождения -->
            <div class="space-y-2">
                <x-ui.label class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.dob') }}
                </x-ui.label>
                <div class="flex items-center gap-2">
                    <!-- День -->
                    <x-ui.select class="min-w-[5rem]" wire:model.live="birth_day">
                        <x-ui.select-trigger class="w-full bg-input border-border">
                            <x-ui.select-value placeholder="{{ __('auth.day') }}" />
                        </x-ui.select-trigger>
                        <x-ui.select-content side="top" align="end"
                            class="max-h-[24rem] min-w-[var(--radix-select-trigger-width)] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/50">
                            @foreach ($this->days as $value => $label)
                                <x-ui.select-item value="{{ $value }}">{{ $label }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>

                    <!-- Месяц -->
                    <x-ui.select class="w-full" wire:model.live="birth_month">
                        <x-ui.select-trigger class="w-full bg-input border-border">
                            <x-ui.select-value placeholder="{{ __('auth.month') }}" />
                        </x-ui.select-trigger>
                        <x-ui.select-content side="top" align="end"
                            class="max-h-[24rem] min-w-[var(--radix-select-trigger-width)] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/50">
                            @foreach ($this->months as $value => $label)
                                <x-ui.select-item value="{{ $value }}">{{ ucfirst($label) }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>

                    <!-- Год -->
                    <x-ui.select class="w-full" wire:model.live="birth_year">
                        <x-ui.select-trigger class="w-full bg-input border-border">
                            <x-ui.select-value placeholder="{{ __('auth.year') }}" />
                        </x-ui.select-trigger>
                        <x-ui.select-content side="top" align="end"
                            class="max-h-[24rem] min-w-[var(--radix-select-trigger-width)] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/50">
                            @foreach ($this->years as $value => $label)
                                <x-ui.select-item value="{{ $value }}">{{ $label }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>
                </div>
                @error('birth_day')
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
                @error('birth_month')
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
                @error('birth_year')
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <!-- Кнопка Далее (Шаг 1) -->
            <x-ui.button type="submit"
                x-bind:disabled="!($wire.name && $wire.gender && $wire.birth_day && $wire.birth_month && $wire.birth_year)"
                class="w-full py-3 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-primary">
                {{ __('common.next') }}
            </x-ui.button>
        </form>
       @elseif ($step === 2)
        <!-- ЭТАП 2 -->
        <form wire:submit="step2Next" class="space-y-6">

            <h2 class="text-lg font-medium text-center text-muted-foreground">{{ __('auth.goal') }}</h2>

           <div class="max-w-2xl mx-auto">
                <!-- 1-й ряд: Друзья, Семья, Путешествия -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div wire:click="$set('dating_goal', 'friends')"
                        class="cursor-pointer p-4 border rounded-lg flex flex-col items-center gap-3 transition-all duration-200 {{ $dating_goal === 'friends' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20' }}">
                        <svg class="w-12 h-12 {{ $dating_goal === 'friends' ? 'text-primary' : 'text-muted-foreground' }}"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        <span class="text-sm font-medium text-center {{ $dating_goal === 'friends' ? 'text-primary' : 'text-foreground' }}">{{ __('auth.friends') }}</span>
                    </div>

                    <div wire:click="$set('dating_goal', 'family')"
                        class="cursor-pointer p-4 border rounded-lg flex flex-col items-center gap-3 transition-all duration-200 {{ $dating_goal === 'family' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20' }}">
                        <svg class="w-12 h-12 {{ $dating_goal === 'family' ? 'text-primary' : 'text-muted-foreground' }}"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 21h18M8.25 12h7.5M6 6.75h12M6 12V6.75M18 12V6.75M4.5 3.75h15M4.5 21v-8.25" />
                        </svg>
                        <span class="text-sm font-medium text-center {{ $dating_goal === 'family' ? 'text-primary' : 'text-foreground' }}">{{ __('auth.family') }}</span>
                    </div>

                    <div wire:click="$set('dating_goal', 'travel')"
                        class="cursor-pointer p-4 border rounded-lg flex flex-col items-center gap-3 transition-all duration-200 {{ $dating_goal === 'travel' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20' }}">
                        <svg class="w-12 h-12 {{ $dating_goal === 'travel' ? 'text-primary' : 'text-muted-foreground' }}"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                        <span class="text-sm font-medium text-center {{ $dating_goal === 'travel' ? 'text-primary' : 'text-foreground' }}">{{ __('auth.travel') }}</span>
                    </div>
                </div>

                <!-- 2-й ряд: Романтика, Свободные отношения -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-md mx-auto">
                    <div wire:click="$set('dating_goal', 'romantic')"
                        class="cursor-pointer p-4 border rounded-lg flex flex-col items-center gap-3 transition-all duration-200 {{ $dating_goal === 'romantic' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20' }}">
                        <svg class="w-12 h-12 {{ $dating_goal === 'romantic' ? 'text-primary' : 'text-muted-foreground' }}"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                        </svg>
                        <span class="text-sm font-medium text-center {{ $dating_goal === 'romantic' ? 'text-primary' : 'text-foreground' }}">{{ __('auth.romantic') }}</span>
                    </div>

                    <div wire:click="$set('dating_goal', 'casual')"
                        class="cursor-pointer p-4 border rounded-lg flex flex-col items-center gap-3 transition-all duration-200 {{ $dating_goal === 'casual' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20' }}">
                        <svg class="w-12 h-12 {{ $dating_goal === 'casual' ? 'text-primary' : 'text-muted-foreground' }}"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                        </svg>
                        <span class="text-sm font-medium text-center {{ $dating_goal === 'casual' ? 'text-primary' : 'text-foreground' }}">{{ __('auth.casual') }}</span>
                    </div>
                </div>
            </div>
            @error('dating_goal')
                <p class="text-sm text-destructive text-center">{{ $message }}</p>
            @enderror

            <div class="flex gap-2">
                <x-ui.button type="button" variant="outline" wire:click="back" class="flex-1 py-3 min-w-0">
                    {{ __('common.back') }}
                </x-ui.button>

                <!-- Кнопка Далее (Шаг 2) -->
                <x-ui.button type="submit" x-bind:disabled="!$wire.dating_goal"
                    class="flex-1 py-3 min-w-0 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-primary">
                    {{ __('common.next') }}
                </x-ui.button>
            </div>
        </form>
    @elseif ($step === 3)
        <!-- ЭТАП 3 -->
        <form wire:submit="register" class="space-y-6">

            <h2 class="text-lg font-medium text-center text-muted-foreground">{{ __('auth.final_step') }}</h2>

            <!-- Город -->
            <div class="space-y-2">
                <x-ui.label for="city" class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.city') }}
                </x-ui.label>
                <x-ui.input wire:model="city" id="city" name="city" type="text" required
                    class="w-full bg-input border-border focus-visible:ring-ring "
                    placeholder="{{ __('auth.ph_city') }}" />
                @error('city')
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div class="space-y-2">
                <x-ui.label for="email" class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.email') }}
                </x-ui.label>
                <x-ui.input wire:model="email" id="email" name="email" type="email" required
                    autocomplete="username" class="w-full bg-input border-border focus-visible:ring-ring "
                    placeholder="{{ __('auth.ph_email') }}" />
                @error('email')
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <!-- Пароль -->
            <div class="space-y-2">
                <x-ui.label for="password" class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.password') }}
                </x-ui.label>
                <x-ui.input wire:model="password" id="password" name="password" type="password" required
                    autocomplete="new-password" class="w-full bg-input border-border focus-visible:ring-ring "
                    placeholder="{{ __('auth.ph_create_password') }}" />
                @error('password')
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-2">
                <x-ui.button type="button" variant="outline" wire:click="back" class="flex-1 py-3 min-w-0">
                    {{ __('common.back') }}
                </x-ui.button>

                <!-- Кнопка Регистрации (Шаг 3) -->
                <x-ui.button type="submit" x-bind:disabled="!($wire.city && $wire.email && $wire.password)"
                    class="flex-1 py-3 min-w-0 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-primary">
                    {{ __('common.register') }}
                </x-ui.button>
            </div>
        </form>
    @endif
</div>