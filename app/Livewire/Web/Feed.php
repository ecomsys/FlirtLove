<?php

namespace App\Livewire\Web;

use App\Services\Search\UserSearchService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

class Feed extends Component
{
    use WithPagination;

    #[Url(as: 'gender', except: 'any')]
    public string $gender = 'any';
    
    #[Url(as: 'online', except: false)]
    public bool $onlineOnly = false;

    public function updatedGender(): void { $this->resetPage(); }
    public function updatedOnlineOnly(): void { $this->resetPage(); }

    public function render(UserSearchService $searchService)
    {
        $filters = [
            'gender' => $this->gender,
            'online_only' => $this->onlineOnly,
        ];

        $users = $searchService->search(auth()->user(), $filters);

        // Используем наш новый единый Layout
        return view('livewire.web.feed', [
            'users' => $users
        ])->layout('layouts.web');
    }
}