<?php

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $promoteSearch = '';
    public array $selectedRoles = [];
    
    // Доступные роли (public для доступа из Blade)
    public array $rolesList = [
        'admin' => 'Суперадмин',
        'moderator' => 'Модератор',
        'support' => 'Саппорт',
    ];

    /**
     * Авторизация: только админы могут управлять ролями.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    /**
     * Проверка на владельца проекта (иммунитет к увольнению).
     */
    private function isFounder(User $user): bool
    {
        return in_array($user->id, config('app.founders', []));
    }

    /**
     * Мгновенное повышение юзера в Staff.
     */
    public function promoteToRole(int $userId, string $role): void
    {
        if (!in_array($role, ['admin', 'moderator', 'support'])) return;

        try {
            $user = User::find($userId);
            if (!$user || $user->isStaff()) return;

            $oldRole = $user->role;
            $user->update(['role' => $role]);

            AdminLog::record('user.role_change', $user, auth()->user(), 
                ['role' => $oldRole], 
                ['role' => $role]
            );

            Log::info("Админ повысил пользователя", [
                'user_id' => $userId, 
                'new_role' => $role, 
                'admin_id' => auth()->id()
            ]);

            $this->selectedRoles[$userId] = $role;
            unset($this->staffMembers); // Сбрасываем кэш

            $this->dispatch('show-toast', type: 'success', message: "{$user->name} повышен до «{$this->rolesList[$role]}»!");
        } catch (\Exception $e) {
            Log::error("Ошибка при повышении пользователя: " . $e->getMessage(), ['user_id' => $userId]);
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при повышении!');
        }
    }

    /**
     * Понижение сотрудника до обычного юзера.
     */
    public function demoteToUser(int $userId): void
    {
        try {
            $user = User::find($userId);
            if (!$user || !$user->isStaff()) return;

            // Защита владельцев и самого себя
            if ($this->isFounder($user)) {
                $this->dispatch('show-toast', type: 'error', message: 'Владельцев проекта нельзя разжаловать!');
                return;
            }

            if ($user->id === auth()->id()) {
                $this->dispatch('show-toast', type: 'error', message: 'Нельзя разжаловать самого себя!');
                return;
            }

            $oldRole = $user->role;
            $user->update(['role' => 'user']);

            // Уничтожаем сессии уволенного (моментальный логаут из админки)
            DB::table('sessions')->where('user_id', $user->id)->delete();

            AdminLog::record('user.role_change', $user, auth()->user(), 
                ['role' => $oldRole], 
                ['role' => 'user']
            );

            Log::info("Админ разжаловал пользователя", [
                'user_id' => $userId, 
                'admin_id' => auth()->id()
            ]);

            unset($this->selectedRoles[$userId]);
            unset($this->staffMembers); // Сбрасываем кэш для обновления таблицы

            $this->dispatch('show-toast', type: 'info', message: "{$user->name} разжалован в обычные юзеры.");
        } catch (\Exception $e) {
            Log::error("Ошибка при понижении пользователя: " . $e->getMessage(), ['user_id' => $userId]);
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при понижении!');
        }
    }

    /**
     * Пакетное сохранение измененных ролей.
     */
    public function saveAllRoles(): void
    {
        $updatedCount = 0;
        $changedUserIds = []; // Массив для сбора ID юзеров с измененными ролями

        try {
            DB::Transaction(function () use (&$updatedCount, &$changedUserIds) {
                foreach ($this->selectedRoles as $userId => $newRole) {
                    if (!in_array($newRole, ['admin', 'moderator', 'support'])) continue;

                    $user = User::find($userId);
                    if (!$user || !$user->isStaff()) continue;

                    // Иммунитет для владельцев и себя
                    if ($this->isFounder($user) || $user->id === auth()->id()) continue;

                    // Пропуск, если роль не изменилась
                    if ($user->role === $newRole) continue;

                    $oldRole = $user->role;
                    $user->update(['role' => $newRole]);

                    AdminLog::record('user.role_change', $user, auth()->user(), 
                        ['role' => $oldRole], 
                        ['role' => $newRole]
                    );

                    $changedUserIds[] = $user->id;
                    $updatedCount++;
                }
            });

            // ФИКС БЕЗОПАСНОСТИ: Уничтожаем сессии у тех, чьи роли изменились
            if (!empty($changedUserIds)) {
                DB::table('sessions')->whereIn('user_id', $changedUserIds)->delete();
                Log::info("Массовое обновление ролей", [
                    'admin_id' => auth()->id(), 
                    'affected_users' => $changedUserIds
                ]);
                
                $this->dispatch('show-toast', type: 'success', message: "Успешно сохранено! Ролей изменено: {$updatedCount}");
                unset($this->staffMembers);
            } else {
                $this->dispatch('show-toast', type: 'info', message: 'Изменений для сохранения нет.');
            }
        } catch (\Exception $e) {
            Log::error("Ошибка при массовом сохранении ролей: " . $e->getMessage(), [
                'admin_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при сохранении!');
        }
    }
 
    #[Computed]
    public function staffMembers()
    {
        $founders = config('app.founders', []);
        
        // Защита от SQL-инъекций: строго приводим ID к int
        $founderCase = !empty($founders) 
            ? "CASE WHEN id IN (" . implode(',', array_map('intval', $founders)) . ") THEN 0 ELSE 1 END" 
            : "1";

        $staff = User::whereNot('role', 'user')
            ->with(['profile', 'photos']) // Eager load для аватарок
            ->orderByRaw($founderCase) // Владельцы всегда вверху
            ->orderBy('id', 'asc')
            ->paginate(20);

        // Инициализация массива для wire:model в селектах.
        // Computed кэшируется, поэтому мутация здесь безопасна и выполнится 1 раз.
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
            return collect(); // Возвращаем пустую коллекцию
        }

        // Кросс-БД поиск (ilike для Postgres, like для MySQL)
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

    /**
     * Радиоктивная индикация кнопки "Сохранить".
     */
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
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-shield-check class="w-6 h-6" />
            Роли и Админы
        </h1>
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
                                    <x-ui.alert-dialog>
                                        <x-ui.alert-dialog-trigger>
                                            <x-ui.button variant="destructive" size="xs">
                                                <x-lucide-user-minus class="w-3 h-3" /> Уволить
                                            </x-ui.button>
                                        </x-ui.alert-dialog-trigger>
                                        <x-ui.alert-dialog-content>
                                            <x-ui.alert-dialog-header>
                                                <x-ui.alert-dialog-title>Разжаловать {{ $staff->name }}?</x-ui.alert-dialog-title>
                                                <x-ui.alert-dialog-description>
                                                    Он потеряет доступ к админке и снова появится в ленте дейтинга как обычный юзер.
                                                </x-ui.alert-dialog-description>
                                            </x-ui.alert-dialog-header>
                                            <x-ui.alert-dialog-footer>
                                                <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                                                <x-ui.alert-dialog-action wire:click="demoteToUser({{ $staff->id }})">Разжаловать</x-ui.alert-dialog-action>
                                            </x-ui.alert-dialog-footer>
                                        </x-ui.alert-dialog-content>
                                    </x-ui.alert-dialog>
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