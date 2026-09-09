<?php

use App\Enums\UserEventType;
use App\Models\User;
use App\Models\UserEvent;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    // Убрали хелпер getEventTitle, он больше не нужен!

    #[Computed]
    public function events()
    {
        return UserEvent::where('user_id', $this->userId)
            ->latest()
            ->paginate(20);
    }

    #[On('user-action-performed')] 
    public function refreshEvents(): void
    {
        unset($this->events);
    }
}; 
?>


<div class="space-y-4">
    
    @if($this->events->isEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-dashed border-border text-center text-xs text-muted-foreground">
            Лента событий пуста.
        </div>
    @else
        <div class="space-y-4 relative before:absolute before:left-[15px] before:top-2 before:bottom-2 before:w-px before:bg-border">
            @foreach($this->events as $event)
                @php 
                    // ФИКС: Получаем весь маппинг из Enum одной строкой
                    $typeEnum = UserEventType::tryFrom($event->type);
                    $icon = $typeEnum?->icon() ?? 'activity';
                    $color = $typeEnum?->color() ?? 'bg-primary/10 text-primary';
                    $title = $typeEnum?->label() ?? ucfirst(str_replace('_', ' ', $event->type));
                @endphp
                
                <div class="flex gap-4 items-start relative" wire:key="event-{{ $event->id }}">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 shrink-0 {{ $color }}">
                        <x-dynamic-component component="lucide-{{ $icon }}" class="w-4 h-4" />
                    </div>
                    
                    <div class="flex-1 pt-1 min-w-0">
                        <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{ $title }}</span>
                                <span class="text-[10px] text-muted-foreground font-mono bg-muted px-1.5 py-0.5 rounded">{{ $event->type }}</span>
                            </div>
                            <span class="text-[10px] text-muted-foreground">{{ $event->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        
                        @if(!empty($event->properties))
                            <details class="group mt-2">
                                <summary class="cursor-pointer text-xs text-blue-500 hover:underline flex items-center gap-1 select-none list-none">
                                    <x-lucide-chevron-right class="w-3 h-3 group-open:rotate-90 transition-transform" />
                                    Показать детали
                                </summary>
                                <div class="mt-2 bg-muted/20 border border-border rounded-md p-3 space-y-1">
                                    @foreach($event->properties as $key => $value)
                                        <div class="flex justify-between items-start text-xs" wire:key="prop-{{ $key }}">
                                            <span class="text-muted-foreground capitalize mr-2">{{ str_replace('_', ' ', $key) }}:</span>
                                            <span class="font-medium text-foreground text-right">{{ is_array($value) ? json_encode($value) : $value }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $this->events->links('partials.pagination') }}
        </div>
    @endif
</div>