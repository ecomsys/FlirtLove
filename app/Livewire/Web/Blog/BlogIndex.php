<?php

namespace App\Livewire\Web\Blog;

use App\Models\BlogPost;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('layouts.web')]
class BlogIndex extends Component
{
    use WithPagination;

    public function render()
    {
        // Выводим только опубликованные посты
        $posts = BlogPost::where('status', 'published')
                    ->latest()
                    ->paginate(9);

        return view('livewire.web.blog.index', [
            'posts' => $posts
        ]);
    }
}