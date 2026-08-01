<?php

use App\Models\Verification;
use App\Models\AdminLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'status', except: 'pending')]
    public string $statusFilter = 'pending';
    
    public int $perPage = 10;

    // Состояние модалки отклонения
    public ?int $rejectingVerificationId = null;
    public string $rejectReason = '';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    // === ДЕЙСТВИЯ ===

    public function approve(int $verificationId): void
    {
        $verification = Verification::find($verificationId);
        if (!$verification || $verification->status !== 'pending') return;

        // Наш метод в модели сам ставит статус и user->is_verified = true
        $verification->markAsApproved(auth()->id());
        
        AdminLog::record('verification.approve', $verification, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: 'Верификация одобрена! Юзер получил галочку.');
    }

    public function openRejectModal(int $verificationId): void
    {
        $this->rejectingVerificationId = $verificationId;
        $this->rejectReason = '';
    }

    public function rejectVerification(): void
    {
        $this->validate(['rejectReason' => 'required|string']);

        $verification = Verification::find($this->rejectingVerificationId);
        if (!$verification) return;

        $verification->markAsRejected(auth()->id(), $this->rejectReason);
        
        AdminLog::record('verification.reject', $verification, auth()->user(), ['status' => 'pending'], ['status' => 'rejected', 'reason' => $this->rejectReason]);

        $this->rejectingVerificationId = null;
        $this->rejectReason = '';
        $this->dispatch('show-toast', type: 'error', message: 'Заявка отклонена');
    }

    // === ВЫВОД ДАННЫХ ===

    public function with(): array
    {
        $verifications = Verification::with([
            'user' => function ($q) {
                // Подгружаем юзера и его одобренные фото для сравнения лиц
                $q->select('id', 'name', 'status', 'is_verified')
                  ->with(['photos' => function ($sq) {
                      $sq->where('status', 'approved')
                         ->orderBy('is_primary', 'desc')
                         ->select('id', 'user_id', 'path_thumb', 'path_medium', 'is_primary')
                         ->limit(3);
                  }]);
            },
            'photo' // Само фото верификации
        ])
        ->whereHas('user', fn($q) => $q->excludeStaff())
        ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
        ->latest()
        ->paginate($this->perPage);

        $counts = Verification::whereHas('user', fn($q) => $q->excludeStaff())
            ->selectRaw("
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                COUNT(*) as total
            ")->first();

        return [
            'verifications' => $verifications,
            'pendingCount' => (int) ($counts->pending ?? 0),
            'approvedCount' => (int) ($counts->approved ?? 0),
            'rejectedCount' => (int) ($counts->rejected ?? 0),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold">Очередь: Верификация 🛡️</h1>
        @if ($pendingCount > 0)
            <span class="bg-blue-500/10 text-blue-600 px-3 py-1 rounded-full text-sm font-medium">
                В очереди: {{ $pendingCount }}
            </span>
        @endif
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-2">
            <x-ui.button wire:click="$set('statusFilter', 'pending')" variant="{{ $statusFilter == 'pending' ? 'default' : 'secondary' }}">
                Ожидают <x-ui.badge>{{ $pendingCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'approved')" variant="{{ $statusFilter == 'approved' ? 'default' : 'secondary' }}">
                Одобрены <x-ui.badge>{{ $approvedCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'rejected')" variant="{{ $statusFilter == 'rejected' ? 'default' : 'secondary' }}">
                Отклонены <x-ui.badge>{{ $rejectedCount }}</x-ui.badge>
            </x-ui.button>
        </div>
    </div>

    <!-- Список заявок -->
    @if($verifications->isEmpty())
        <div class="bg-card border border-border rounded-lg p-16 text-center">
            <x-lucide-check-circle class="w-12 h-12 mx-auto text-muted-foreground mb-4" />
            <h3 class="text-lg font-medium">Очередь пуста!</h3>
            <p class="text-muted-foreground mt-1">Нет заявок на верификацию.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($verifications as $verification)
                <div wire:key="verification-{{ $verification->id }}" class="bg-card border border-border rounded-lg overflow-hidden">
                    <div class="p-4 border-b border-border flex items-center justify-between bg-muted/20">
                        <div class="flex items-center gap-3">
                            <x-avatar src="{{ $verification->user?->avatar_url }}" name="{{ $verification->user?->name }}" size="lg" />
                            <div>
                                <a href="{{ route('admin.users.show', $verification->user_id) }}" wire:navigate class="font-semibold text-foreground hover:text-primary flex items-center gap-2">
                                    {{ $verification->user?->name }}
                                    @if($verification->user?->is_verified) <x-lucide-badge-check class="w-4 h-4 text-blue-500" /> @endif
                                </a>
                                <div class="text-xs text-muted-foreground">
                                    ID: {{ $verification->user_id }} • Подано: {{ $verification->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>

                        @if($verification->status === 'pending')
                            <div class="flex gap-2">
                                <x-ui.button wire:click="approve({{ $verification->id }})" variant="success" size="sm">
                                    <x-lucide-check class="w-4 h-4" /> Одобрить
                                </x-ui.button>
                                <x-ui.button wire:click="openRejectModal({{ $verification->id }})" variant="destructive" size="sm">
                                    <x-lucide-x class="w-4 h-4" /> Отклонить
                                </x-ui.button>
                            </div>
                        @elseif($verification->status === 'rejected')
                            <x-ui.badge variant="destructive">Отклонено: {{ $verification->reject_reason }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="success">Верифицирован</x-ui.badge>
                        @endif
                    </div>

                    <!-- Блок сравнения фото -->
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Левая колонка: Фото из профиля юзера -->
                        <div>
                            <h3 class="text-sm font-semibold text-muted-foreground mb-3">Фото из профиля</h3>
                            <div class="flex gap-2">
                                @if($verification->user?->photos->isEmpty())
                                    <div class="w-24 h-24 bg-muted rounded-lg flex items-center justify-center text-xs text-muted-foreground">Нет фото</div>
                                @else
                                    @foreach($verification->user->photos as $profilePhoto)
                                        <a href="{{ $profilePhoto->original_url }}" data-fancybox="profile-{{ $verification->user_id }}" class="w-24 h-24 rounded-lg overflow-hidden border-2 border-border hover:border-primary transition-colors">
                                            <img src="{{ $profilePhoto->thumb_url }}" class="w-full h-full object-cover">
                                        </a>
                                    @endforeach
                                @endif
                            </div>
                        </div>

                        <!-- Правая колонка: Фото на верификацию -->
                        <div>
                            <h3 class="text-sm font-semibold text-muted-foreground mb-3">Фото для верификации</h3>
                            @if($verification->photo)
                                <a href="{{ $verification->photo->original_url }}" data-fancybox="verify-{{ $verification->id }}" class="block max-w-[200px] aspect-square bg-muted rounded-lg overflow-hidden border-2 border-blue-500/50 hover:border-blue-500 transition-colors">
                                    <img src="{{ $verification->photo->medium_url }}" class="w-full h-full object-cover">
                                </a>
                            @else
                                <div class="w-24 h-24 bg-muted rounded-lg flex items-center justify-center text-xs text-destructive">Фото удалено</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $verifications->links('partials.pagination') }}</div>
    @endif

    <!-- МОДАЛКА ОТКЛОНЕНИЯ ВЕРИФИКАЦИИ -->
    <div x-data="{ show: @entangle('rejectingVerificationId') }" x-show="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
        <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md shadow-xl">
            <h3 class="text-lg font-semibold mb-4">Причина отклонения верификации</h3>
            
            <div class="space-y-2 mb-4">
                <select wire:model="rejectReason" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                    <option value="">Выберите причину...</option>
                    <option value="blurry">Фото размыто / плохое качество</option>
                    <option value="no_face">Лицо не видно или закрыто</option>
                    <option value="no_code">Нет листочка с кодом</option>
                    <option value="fake">Фейковое / Фотошоп</option>
                    <option value="other">Другое</option>
                </select>
                @error('rejectReason') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button wire:click="$set('rejectingVerificationId', null)">Отмена</x-ui.button>
                <x-ui.button wire:click="rejectVerification" variant="destructive">Отклонить</x-ui.button>
            </div>
        </div>
    </div>
</div>