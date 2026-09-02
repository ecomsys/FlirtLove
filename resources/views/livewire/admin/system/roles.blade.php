<?php

use App\Actions\Admin\ManageUserRolesAction;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $promoteSearch = '';
    public array $selectedRoles = [];
    
    public array $rolesList = [
        'admin' => 'Суперадмин',
        'moderator' => 'Модератор',
        'support' => 'Саппорт',
    ];

    public string $backUrl = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');
    }

    private function isFounder(User $user): bool
    {
        return in_array($user->id, config('app.founders', []));
    }

    public function promoteToRole(int $userId, string $role, ManageUserRolesAction $action): void
    {
        try {
            $user = User::find($userId);
            if (!$user) return;

            $success = $action->promote($user, $role, auth()->user());

            if ($success) {
                $this->selectedRoles[$userId] = $role;
                unset($this->staffMembers);
                $this->dispatch('show-toast', type: 'success', message: "{$user->name} повышен до «{$this->rolesList[$role]}»!");
            }
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при повышении!');
        }
    }

    public function demoteToUser(int $userId, ManageUserRolesAction $action): void
    {
        try {
            $user = User::find($userId);
            if (!$user) return;

            if ($this->isFounder($user)) {
                $this->dispatch('show-toast', type: 'error', message: 'Владельцев проекта нельзя разжаловать!');
                return;
            }

            if ($user->id === auth()->id()) {
                $this->dispatch('show-toast', type: 'error', message: 'Нельзя разжаловать самого себя!');
                return;
            }

            $success = $action->demote($user, auth()->user());

            if ($success) {
                unset($this->selectedRoles[$userId]);
                unset($this->staffMembers);
                $this->dispatch('show-toast', type: 'info', message: "{$user->name} разжалован в обычные юзеры.");
            }
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при понижении!');
        }
    }

    public function saveAllRoles(ManageUserRolesAction $action): void
    {
        try {
            $founders = config('app.founders', []);
            $updatedCount = $action->batchUpdate($this->selectedRoles, auth()->user(), $founders);

            if ($updatedCount > 0) {
                $this->dispatch('show-toast', type: 'success', message: "Успешно сохранено! Ролей изменено: {$updatedCount}");
                unset($this->staffMembers);
            } else {
                $this->dispatch('show-toast', type: 'info', message: 'Изменений для сохранения нет.');
            }
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при сохранении!');
        }
    }
 
    #[Computed]
    public function staffMembers()
    {
        $founders = config('app.founders', []);
        $founderCase = !empty($founders) 
            ? "CASE WHEN id IN (" . implode(',', array_map('intval', $founders)) . ") THEN 0 ELSE 1 END" 
            : "1";

        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);

        $staff = User::withTrashed()
            ->whereNot('role', 'user')
            ->with(['profile', 'photos' => $avatarQuery]) 
            ->orderByRaw($founderCase)
            ->orderBy('id', 'asc')
            ->paginate(20);

        foreach ($staff as $user) {
            if (!isset($this->selectedRoles[$user->id])) {
                $this->selectedRoles[$user->id] = $user->role;
            }
        }

        return $staff;
    }

    #[Computed]
    public function candidatesForPromotion()
    {
        if (empty($this->promoteSearch)) {
            return collect();
        }

        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return User::excludeStaff()
            ->with('photos')
            ->where(function ($q) use ($searchOperator) {
                $q->where('name', $searchOperator, "%{$this->promoteSearch}%")
                  ->orWhere('email', $searchOperator, "%{$this->promoteSearch}%");
            })
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function hasUnsavedChanges(): bool
    {
        if (!isset($this->staffMembers)) return false;

        foreach ($this->staffMembers as $staff) {
            if ($this->isFounder($staff) || $staff->id === auth()->id()) continue;

            $currentSelectedRole = $this->selectedRoles[$staff->id] ?? $staff->role;
            
            if ($currentSelectedRole !== $staff->role) {
                return true;
            }
        }

        return false;
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Заголовок страницы -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-shield-check class="w-6 h-6" />
                Роли и Админы
            </h1>
        </div>
    </div>

    <!-- Блок Повышения (Поиск юзеров) -->
    <div class="bg-card border border-border rounded-lg p-6 space-y-4">
        <h2 class="text-lg font-semibold flex items-center gap-2">
            <x-lucide-user-plus class="w-5 h-5" />
            Назначить сотрудника
        </h2>
        <p class="text-sm text-muted-foreground">Найдите обычного юзера по имени или email, чтобы повысить его. Как только он станет сотрудником, он автоматически исчезнет из ленты дейтинга (Ghost Mode).</p>

        <div x-data="{ promoteOpen: false }" class="relative w-full max-w-xl">
            <x-ui.input 
                id="promoteSearch" 
                name="promoteSearch" 
                wire:model.live.debounce.300ms="promoteSearch" 
                type="search" 
                placeholder="Введите имя или email юзера..." 
                class="pl-9 pr-8" 
                x-on:input="promoteOpen = true"
                x-on:keydown.escape="promoteOpen = false; $wire.set('promoteSearch', '')"
                autocomplete="off"
            />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />

            @if(!empty($promoteSearch))
                <!-- Единый контейнер. Без wire:loading, чтобы не конфликтовать с Alpine.
                     Рамка появляется только когда внутри есть контент (юзеры или текст "не найдено") -->
                <div 
                    x-show="promoteOpen" 
                    x-on:click.outside="promoteOpen = false; $wire.set('promoteSearch', '')" 
                    x-transition 
                    class="absolute z-50 top-full mt-1 w-full bg-card border border-border rounded-lg shadow-xl overflow-hidden"
                >
                    @if($this->candidatesForPromotion->isNotEmpty())
                        @foreach($this->candidatesForPromotion as $candidate)
                            <div wire:key="candidate-{{ $candidate->id }}" class="flex items-center justify-between p-3 border-b border-border last:border-b-0 hover:bg-muted/10 bg-background">
                                 <a href="{{ route('admin.users.show', $candidate->id) }}" wire:navigate class="flex items-center gap-3 text-sm font-medium hover:text-primary transition-colors">                                                                                                        
                                    <x-avatar src="{{ $candidate->avatar_url }}" name="{{ $candidate->name }}" size="sm" userId="{{ $candidate->id }}" showStatus="true" :isOnline="$candidate->is_online" />
                                    <div>
                                        <div class="text-sm font-medium">{{ $candidate->name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ $candidate->email }}</div>
                                    </div>                            
                                </a>
                                <div class="flex items-center gap-1">
                                    <x-ui.button wire:click="promoteToRole({{ $candidate->id }}, 'support'); promoteOpen = false; $wire.set('promoteSearch', '')" variant="outline" size="xs" class="text-blue-500 border-blue-500/30 hover:bg-blue-500/10">
                                        <x-lucide-headset class="w-3 h-3" /> Саппорт
                                    </x-ui.button>
                                    <x-ui.button wire:click="promoteToRole({{ $candidate->id }}, 'moderator'); promoteOpen = false; $wire.set('promoteSearch', '')" variant="outline" size="xs" class="text-yellow-500 border-yellow-500/30 hover:bg-yellow-500/10">
                                        <x-lucide-shield class="w-3 h-3" /> Модератор
                                    </x-ui.button>
                                    <x-ui.button wire:click="promoteToRole({{ $candidate->id }}, 'admin'); promoteOpen = false; $wire.set('promoteSearch', '')" variant="outline" size="xs" class="text-red-500 border-red-500/30 hover:bg-red-500/10">
                                        <x-lucide-crown class="w-3 h-3" /> Админ
                                    </x-ui.button>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <!-- Если никого нет, рендерим этот блок. У него есть p-3, так что рамка не будет тонкой полоской! -->
                        <div class="p-3 text-sm text-muted-foreground text-center">
                            Юзеры не найдены
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
    
    <!-- Таблица текущих сотрудников -->
    <div class="bg-card border border-border rounded-lg overflow-hidden">
        <!-- Шапка таблицы с кнопкой сохранения -->
        <div class="p-6 border-b border-border flex items-center justify-between flex-wrap gap-4">
            <h2 class="text-lg font-semibold">Текущий персонал</h2>
            
            <x-ui.button wire:click="saveAllRoles" variant="{{ $this->hasUnsavedChanges ? 'success' : 'secondary' }}" size="sm" class="relative">
                <span wire:loading.remove wire:target="saveAllRoles"><x-lucide-save class="w-4 h-4" /></span>
                <span wire:loading wire:target="saveAllRoles"><x-lucide-loader-2 class="w-4 h-4 animate-spin" /></span>
                
                @if($this->hasUnsavedChanges) Сохранить @else Сохранено @endif               
            </x-ui.button>
        </div>

        <div class="px-6">
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head>Сотрудник</x-ui.table-head>
                        <x-ui.table-head>Email</x-ui.table-head>
                        <x-ui.table-head>Роль</x-ui.table-head>
                        <x-ui.table-head>Дата назначения</x-ui.table-head>
                        <x-ui.table-head class="text-right">Управление</x-ui.table-row>
                    </x-ui.table-row>
                </x-ui.table-header>

                <x-ui.table-body>
                    @forelse ($this->staffMembers as $staff)
                        <x-ui.table-row wire:key="staff-{{ $staff->id }}">
                            <x-ui.table-cell>
                                <div class="flex items-center gap-2">
                                    <x-avatar src="{{ $staff->avatar_url }}" name="{{ $staff->name }}" size="sm" userId="{{ $staff->id }}" showStatus="true" :isOnline="$staff->is_online" />
                                    <a href="{{ route('admin.users.show', $staff->id) }}" wire:navigate class="font-medium text-sm hover:text-primary transition-colors">
                                        <x-user-status-sign :user="$staff" />
                                        {{ $staff->name }}
                                    </a>
                                    
                                    @if($this->isFounder($staff))
                                        <x-ui.badge variant="outline" size="xs" class="border-yellow-500 text-yellow-500">
                                            <x-lucide-crown class="w-3 h-3 mr-1" /> Владелец
                                        </x-ui.badge>
                                    @elseif($staff->id === auth()->id())
                                        <x-ui.badge variant="secondary" size="xs">Вы</x-ui.badge>
                                    @endif
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-sm text-muted-foreground">{{ $staff->email }}</x-ui.table-cell>
                            
                            <!-- Ячейка с ролью -->
                            <x-ui.table-cell>
                                @if($this->isFounder($staff) || $staff->id === auth()->id())
                                    <x-ui.badge variant="destructive" size="sm" class="pointer-events-none">Суперадмин</x-ui.badge>
                                @else
                                    <x-ui.select 
                                        id="role-{{ $staff->id }}" 
                                        name="selectedRoles[{{ $staff->id }}]" 
                                        wire:model.live="selectedRoles.{{ $staff->id }}"
                                    >
                                        <x-ui.select-trigger class="min-w-32 h-8 text-xs">
                                            <x-ui.select-value placeholder="Выберите роль" />
                                        </x-ui.select-trigger>
                                        <x-ui.select-content>
                                            @foreach($this->rolesList as $key => $roleLabel)
                                                <x-ui.select-item wire:key="role-{{ $key }}" value="{{ $key }}">{{ $roleLabel }}</x-ui.select-item>
                                            @endforeach
                                        </x-ui.select-content>
                                    </x-ui.select>
                                @endif
                            </x-ui.table-cell>

                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $staff->created_at->format('d.m.Y') }}
                            </x-ui.table-cell>
                            
                            <!-- Ячейка увольнения -->
                            <x-ui.table-cell class="text-right">
                                @if($this->isFounder($staff))
                                    <span class="text-xs text-muted-foreground flex items-center gap-1 justify-end">
                                        <x-lucide-lock class="w-3 h-3" /> Защищен
                                    </span>
                                @elseif($staff->id !== auth()->id())
                                    <x-ui.button wire:click="demoteToUser({{ $staff->id }})" wire:confirm="Разжаловать {{ $staff->name }}? Он потеряет доступ к админке." variant="destructive" size="xs">
                                        <x-lucide-user-minus class="w-3 h-3" /> Уволить
                                    </x-ui.button>
                                @else
                                    <span class="text-xs text-muted-foreground">Нельзя уволить себя</span>
                                @endif
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="5" class="py-12 text-center text-muted-foreground">Сотрудников нет</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </div>

        <div class="p-4">
            {{ $this->staffMembers->links('partials.pagination') }}
        </div>
    </div>
</div>