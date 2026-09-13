<?php

namespace App\Livewire\Web;

use App\Livewire\Web\Modals\LoginModal;
use App\Services\Search\UserSearchService;
use Illuminate\Support\Facades\Auth; // <-- Добавили
use Illuminate\Support\Facades\Redirect; // <-- Добавили
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

class Home extends Component
{
    use WithPagination;

    #[Url(as: 'gender', except: 'any')]
    public string $gender = 'any';

    #[Url(as: 'online', except: false)]
    public bool $onlineOnly = false;

    // === ДОБАВЛЯЕМ ЭТОТ МЕТОД ===
    public function mount()
    {
        if (request()->query('login') === '1' && ! auth()->check()) {
            $this->dispatch('open-login-modal')->to(LoginModal::class);
        }

        // Если авторизованный юзер — это персонал, шлем его в админку
        if (Auth::check() && in_array(Auth::user()->role, ['admin', 'moderator', 'support'])) {
            return Redirect::route('admin.dashboard');
        }
    }

    // ============================

    public function updatedGender(): void
    {
        $this->resetPage();
    }
    public function updatedOnlineOnly(): void
    {
        $this->resetPage();
    }

    public function render(UserSearchService $searchService)
    {
        $filters = [
            'gender' => $this->gender,
            'online_only' => $this->onlineOnly,
        ];

        $users = $searchService->search(auth()->user(), $filters);

        // Используем наш новый единый Layout
        return view('livewire.web.home.index', [
            'users' => $users
        ])->layout('components.layouts.web');
    }
}
