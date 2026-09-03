<?php

use App\Models\AdminLog;
use App\Models\Diary;
use App\Models\DiaryRubric;
use App\Actions\Admin\ModerateDiaryAction;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public Diary $diary;
    
    public string $status;
    public ?string $rejectReason = null;
    public ?string $diaryRubricId = null; // ФИКС: Переименовали для консистентности
    public bool $isCommentsEnabled;

    /** @var string URL для кнопки "Назад" */
    public string $backUrl = '';

    /**
     * Инициализация компонента.
     */
    public function mount(Diary $diary): void
    {
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);
        
        // ФИКС: Загружаем связь diaryRubric (вместо rubric)
        $diary->load(['user' => fn($q) => $q->withTrashed()->with(['photos' => $avatarQuery]), 'diaryRubric']);

        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        $this->diary = $diary;
        $this->status = $diary->status;
        $this->rejectReason = $diary->reject_reason ?? 'other';
        $this->diaryRubricId = $diary->diary_rubric_id ? (string) $diary->diary_rubric_id : '';
        $this->isCommentsEnabled = $diary->is_comments_enabled;
    }   

    #[Computed]
    public function availableRubrics()
    {
        return DiaryRubric::where(function ($q) {
            $q->whereNull('user_id')->orWhere('user_id', $this->diary->user_id);
        })
        ->orderBy('user_id')
        ->orderBy('sort_order')
        ->get();
    }

    // Хелпер: Валидация и подготовка настроек (Рубрика + Комменты)
    protected function getMetaData(): array
    {
        $this->validate([
            'diaryRubricId' => 'nullable|string',
            'isCommentsEnabled' => 'boolean',
        ]);

        return [
            'diary_rubric_id' => $this->diaryRubricId !== '' ? (int) $this->diaryRubricId : null,
            'is_comments_enabled' => $this->isCommentsEnabled,
        ];
    }

    // Хелпер: Перезагрузка связей чтобы UI не слетал
    protected function reloadRelations(): void
    {
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);
        
        // ФИКС: Загружаем связь diaryRubric
        $this->diary->load(['user' => fn($q) => $q->withTrashed()->with(['photos' => $avatarQuery]), 'diaryRubric']);
    }

    // Сохранение ТОЛЬКО настроек (Рубрика и Комменты)
    public function saveSettings(): void
    {
        try {
            // ФИКС: Берем только нужные поля для лога, чтобы не писать весь текст дневника в БД
            $before = [
                'diary_rubric_id' => $this->diary->getOriginal('diary_rubric_id'), 
                'is_comments_enabled' => $this->diary->getOriginal('is_comments_enabled')
            ];
            
            $this->diary->update($this->getMetaData());
            $this->diary->refresh();
            
            $after = [
                'diary_rubric_id' => $this->diary->diary_rubric_id, 
                'is_comments_enabled' => $this->diary->is_comments_enabled,
                'context' => [
                    'diary_id' => $this->diary->id,
                    'title' => $this->diary->title
                ]
            ];

            AdminLog::record('diary.update', $this->diary, auth()->user(), $before, $after);
            
            $this->reloadRelations();
            $this->dispatch('show-toast', type: 'success', message: 'Настройки (Рубрика/Комментарии) сохранены!');
        } catch (\Exception $e) {
            Log::error("Ошибка сохранения настроек дневника: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    // Войти в режим отклонения
    public function initiateReject(): void
    {
        $this->status = 'rejected';
        $this->rejectReason = 'other';
        $this->reloadRelations();
    }

    // Отменить отклонение
    public function cancelAction(): void
    {
        $this->status = $this->diary->status;
        $this->rejectReason = $this->diary->reject_reason ?? 'other';
        $this->reloadRelations();
    }

    public function approve(ModerateDiaryAction $action): void
    {
        try {
            $action->approve($this->diary, auth()->user(), $this->getMetaData());
            $this->status = 'published';
            $this->rejectReason = 'other';
            $this->reloadRelations();
            $this->dispatch('show-toast', type: 'success', message: 'Запись опубликована! Настройки применены.');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    public function confirmReject(ModerateDiaryAction $action): void
    {
        $this->validate(['rejectReason' => 'required|string']);
        
        try {
            $action->reject($this->diary, auth()->user(), $this->rejectReason, $this->getMetaData());
            $this->reloadRelations();
            $this->dispatch('show-toast', type: 'warning', message: 'Запись отклонена! Настройки применены.');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    public function unpublish(ModerateDiaryAction $action): void
    {
        try {
            $action->unpublish($this->diary, auth()->user(), $this->getMetaData());
            $this->status = 'pending';
            $this->reloadRelations();
            $this->dispatch('show-toast', type: 'info', message: 'Снято с публикации! Настройки применены.');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-3">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors mt-1">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <a href="{{ route('admin.moderation.diary.index') }}" wire:navigate class="hover:text-foreground">Дневники</a>
                    <x-lucide-chevron-right class="w-4 h-4" />
                    <span>Просмотр и модерация</span>
                </div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-book-open class="w-6 h-6" />
                    {{ $diary->title }}
                </h1>
            </div>
        </div>

        <!-- Кнопка сохранения настроек (Рубрика/Комменты) -->
        <x-ui.button wire:click="saveSettings" variant="default" size="sm" wire:target="saveSettings" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="saveSettings" class="flex items-center gap-2">
                <x-lucide-save class="w-4 h-4" /> Сохранить настройки
            </span>
            <span wire:loading wire:target="saveSettings" class="flex items-center gap-2">
                <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Сохранение...
            </span>
        </x-ui.button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        <!-- ЛЕВАЯ КОЛОНКА (Текст поста - только чтение) -->
        <div class="lg:col-span-2">
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm flex flex-col gap-4 h-full">
                <div class="prose prose-sm dark:prose-invert max-w-none text-foreground/90 p-4 bg-background rounded-md border border-border/50 overflow-y-auto min-h-[calc(100vh-10rem)] little-scroll">
                    {!! $diary->body !!}
                </div>
            </div>
        </div>

        <!-- ПРАВАЯ КОЛОНКА (Панель модерации) -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm sticky top-4 space-y-5">
                
                <!-- Карточка автора -->
                <div class="flex items-center gap-3 p-3 bg-muted/30 rounded-md border border-border">
                    @if($diary->user)
                        <x-avatar src="{{ $diary->user->avatar_url }}" name="{{ $diary->user->name }}" size="md" userId="{{ $diary->user->id }}" showStatus="true" :isOnline="$diary->user->is_online" />
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('admin.users.show', $diary->user_id) }}" wire:navigate class="text-sm font-medium hover:text-primary flex items-center gap-1">
                                <x-user-status-sign :user="$diary->user" />
                                <span class="truncate">{{ $diary->user->name }}</span>
                                @if($diary->user->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500 shrink-0" /> @endif
                            </a>
                            <div class="text-xs text-muted-foreground flex items-center gap-2 mt-1">
                                <span class="flex items-center gap-1" title="Просмотры"><x-lucide-eye class="w-3 h-3" /> {{ $diary->views_count }}</span>
                                <span>•</span>
                                <span class="flex items-center gap-1" title="Комментарии"><x-lucide-message-circle class="w-3 h-3" /> {{ $diary->comments_count }}</span>
                                <span>•</span>
                                <span>{{ $diary->created_at->format('d.m.y') }}</span>
                            </div>
                        </div>
                    @else
                        <x-avatar name="Deleted" size="md" />
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-medium text-muted-foreground flex items-center gap-1">
                                <span class="truncate">Пользователь удален</span>
                            </span>
                            <div class="text-xs text-muted-foreground flex items-center gap-2 mt-1">
                                <span class="flex items-center gap-1" title="Просмотры"><x-lucide-eye class="w-3 h-3" /> {{ $diary->views_count }}</span>
                                <span>•</span>
                                <span class="flex items-center gap-1" title="Комментарии"><x-lucide-message-circle class="w-3 h-3" /> {{ $diary->comments_count }}</span>
                                <span>•</span>
                                <span>{{ $diary->created_at->format('d.m.y') }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Блок настроек (Рубрика / Комменты) -->
                <div class="border-t border-border pt-5 space-y-4">
                    <div class="flex flex-col gap-2">
                        <x-ui.label>Рубрика дневника</x-ui.label>
                        <x-ui.select wire:model="diaryRubricId">
                            <x-ui.select-trigger class="w-full"><x-ui.select-value placeholder="Без рубрики" /></x-ui.select-trigger>
                            <x-ui.select-content class="little-scroll">
                                <x-ui.select-item value="">
                                    <span class="flex items-center gap-2 text-muted-foreground">
                                        <x-lucide-minus-circle class="w-4 h-4" /> Без рубрики
                                    </span>
                                </x-ui.select-item>
                                @foreach($this->availableRubrics as $r)
                                    <x-ui.select-item value="{{ $r->id }}">
                                        <span class="flex items-center gap-2">
                                            @if($r->user_id) 
                                                <x-lucide-user class="w-4 h-4 text-muted-foreground" />
                                            @else 
                                                <x-lucide-globe class="w-4 h-4 text-blue-500" />
                                            @endif
                                            {{ $r->name }}
                                        </span>
                                    </x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-md border border-border bg-muted/30">
                        <div class="pr-4">
                            <p class="text-sm font-medium">Комментарии</p>
                            <p class="text-xs text-muted-foreground">{{ $isCommentsEnabled ? 'Разрешены' : 'Запрещены' }}</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="isCommentsEnabled" class="sr-only peer" />
                            <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                        </label>
                    </div>
                </div>

                <!-- ЖЕСТКАЯ РЕАКТИВНАЯ ПАНЕЛЬ МОДЕРАЦИИ -->
                <div class="border-t border-border pt-5">
                    <h3 class="text-sm font-medium mb-4 flex items-center gap-2 text-muted-foreground uppercase tracking-wide">
                        <x-lucide-shield class="w-4 h-4" /> Модерация
                    </h3>

                    @if($status === 'published')
                        <!-- СТАТУС: ОПУБЛИКОВАНО -->
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center justify-center gap-2 p-3 bg-success/10 text-success rounded-md font-medium">
                                <x-lucide-check-circle class="w-5 h-5" /> Запись опубликована
                            </div>
                            <x-ui.button wire:click="unpublish" wire:target="unpublish" variant="warning" size="sm" class="w-full">
                                <span wire:loading.remove wire:target="unpublish" class="flex items-center gap-2 justify-center">
                                    <x-lucide-eye-off class="w-4 h-4" /> Снять с публикации
                                </span>
                                <span wire:loading wire:target="unpublish" class="flex items-center gap-2 justify-center">
                                    <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Снятие...
                                </span>
                            </x-ui.button>
                        </div>

                    @elseif($status === 'rejected' && $this->diary->status !== 'rejected')
                        <!-- РЕЖИМ ОТКЛОНЕНИЯ (Интерфейс выбора причины) -->
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center justify-center gap-2 p-3 bg-destructive/10 text-destructive rounded-md font-medium">
                                <x-lucide-shield-x class="w-5 h-5" /> Выберите причину
                            </div>
                            
                            <div class="flex flex-col gap-2">
                                <x-ui.select wire:model="rejectReason">
                                    <x-ui.select-trigger class="w-full"><x-ui.select-value /></x-ui.select-trigger>
                                    <x-ui.select-content>
                                        @foreach (\App\Enums\DiaryRejectReason::options() as $value => $label)
                                            <x-ui.select-item value="{{ $value }}">{{ $label }}</x-ui.select-item>
                                        @endforeach
                                    </x-ui.select-content>
                                </x-ui.select>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <x-ui.button wire:click="cancelAction" variant="outline" size="sm" class="w-full">Отмена</x-ui.button>
                                <x-ui.button wire:click="confirmReject" wire:target="confirmReject" variant="destructive" size="sm" class="w-full">
                                    <span wire:loading.remove wire:target="confirmReject" class="flex items-center gap-2 justify-center">
                                        <x-lucide-check class="w-4 h-4" /> Подтвердить
                                    </span>
                                    <span wire:loading wire:target="confirmReject" class="flex items-center gap-2 justify-center">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Отклонение...
                                    </span>
                                </x-ui.button>
                            </div>
                        </div>

                    @elseif($status === 'rejected')
                        <!-- СТАТУС: ОТКЛОНЕНО (Уже сохранено в базе) -->
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center justify-center gap-2 p-3 bg-destructive/10 text-destructive rounded-md font-medium">
                                <x-lucide-x-circle class="w-5 h-5" /> Запись отклонена
                            </div>
                            @php $reasonEnum = \App\Enums\DiaryRejectReason::tryFrom($this->diary->reject_reason ?? 'other'); @endphp
                            @if($reasonEnum)
                                <div class="text-center text-sm text-muted-foreground">
                                    Причина: <span class="font-bold text-foreground">{{ $reasonEnum->label() }}</span>
                                </div>
                            @endif
                            <x-ui.button wire:click="approve" wire:target="approve" variant="success" size="sm" class="w-full">
                                <span wire:loading.remove wire:target="approve" class="flex items-center gap-2 justify-center">
                                    <x-lucide-check class="w-4 h-4" /> Одобрить
                                </span>
                                <span wire:loading wire:target="approve" class="flex items-center gap-2 justify-center">
                                    <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Публикация...
                                </span>
                            </x-ui.button>
                        </div>

                    @else
                        <!-- СТАТУС: НА МОДЕРАЦИИ ИЛИ ЧЕРНОВИК -->
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center justify-center gap-2 p-3 bg-warning/10 text-warning rounded-md font-medium">
                                <x-lucide-clock class="w-5 h-5" /> Ожидает модерации
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <x-ui.button wire:click="approve" wire:target="approve" variant="success" size="sm" class="w-full">
                                    <span wire:loading.remove wire:target="approve" class="flex items-center gap-2 justify-center">
                                        <x-lucide-check class="w-4 h-4" /> Одобрить
                                    </span>
                                    <span wire:loading wire:target="approve" class="flex items-center gap-2 justify-center">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Публикация...
                                    </span>
                                </x-ui.button>
                                
                                <x-ui.button wire:click="initiateReject" variant="destructive" size="sm" class="w-full">
                                    <x-lucide-x class="w-4 h-4" /> Отклонить
                                </x-ui.button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>