<?php

use App\Models\AdminLog;
use App\Models\User;
use App\Support\AdminLogMeta;
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

    #[Computed]
    public function logs()
    {
        $userId = $this->userId;

        return AdminLog::where(function ($query) use ($userId) {
                $query->where('loggable_type', User::class)
                      ->where('loggable_id', $userId);
            })
            ->orWhere(function ($query) use ($userId) {
                $query->whereNotNull('participants')
                      ->whereRaw("participants::jsonb @> ?", [json_encode([$userId])]);
            })
            ->with('admin:id,name,email')
            ->latest()
            ->paginate(20);
    }

    #[On('user-action-performed')] 
    public function refreshLogs(): void
    {
        unset($this->logs);
    }
}; 
?>

<div class="space-y-4">
    
    @if($this->logs->isEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-dashed border-border text-center text-xs text-muted-foreground">
            История пуста. С пользователем еще не производилось административных действий.
        </div>
    @else
        <div class="space-y-4 relative before:absolute before:left-[15px] before:top-2 before:bottom-2 before:w-px before:bg-border">
            @foreach($this->logs as $log)
               @php $meta = \App\Support\AdminLogMeta::get($log->action); @endphp
                <div class="flex gap-4 items-start relative" wire:key="log-{{ $log->id }}">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 shrink-0 {{ $meta['iconColor'] }}">
                        <x-dynamic-component component="lucide-{{ $meta['icon'] }}" class="w-4 h-4" />
                    </div>
                    
                    <div class="flex-1 pt-1 min-w-0">
                        <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{ $meta['title'] }}</span>
                                <x-ui.badge variant="{{ $meta['badge']['variant'] }}" size="xs">{{ $meta['badge']['label'] }}</x-ui.badge>
                                <span class="text-[10px] text-muted-foreground font-mono bg-muted px-1.5 py-0.5 rounded">{{ $log->action }}</span>
                            </div>
                            <span class="text-[10px] text-muted-foreground">{{ $log->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        
                        <div class="text-xs text-muted-foreground mb-2">
                            Админ: 
                            @if($log->admin)
                                <a href="{{ route('admin.users.show', $log->admin->id) }}" wire:navigate class="text-primary hover:underline">
                                    {{ $log->admin->name }}
                                </a>
                            @else 
                                Система / Удален
                            @endif
                        </div>

                        {{-- Аккордеон для просмотра изменений (Было / Стало) --}}
                        @if(!empty($log->before) || !empty($log->after))
                            <details class="group mt-2">
                                <summary class="cursor-pointer text-xs text-blue-500 hover:underline flex items-center gap-1 select-none">
                                    <x-lucide-chevron-right class="w-3 h-3 group-open:rotate-90 transition-transform" />
                                    Показать изменения
                                </summary>
                                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 bg-muted/20 border border-border rounded-md p-3">
                                    @if(!empty($log->before))
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase text-destructive mb-1">Было:</span>
                                            <pre class="text-[11px] text-muted-foreground whitespace-pre-wrap break-words font-mono">{{ json_encode($log->before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    @endif
                                    @if(!empty($log->after))
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase text-green-500 mb-1">Стало:</span>
                                            <pre class="text-[11px] text-muted-foreground whitespace-pre-wrap break-words font-mono">{{ json_encode($log->after, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $this->logs->links('partials.pagination') }}
        </div>
    @endif
</div>