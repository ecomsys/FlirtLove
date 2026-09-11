<?php

namespace App\Livewire\Web\Blog;

use App\Models\BlogPost;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.web')]
class BlogShow extends Component
{
    public BlogPost $post;

    public function mount(BlogPost $post)
    {
        // Если статья не опубликована, отдаем 404
        if ($post->status !== 'published') {
            abort(404);
        }
        
        $this->post = $post;
        
        // Увеличиваем счетчик просмотров
        $post->incrementViews();
    }

    public function render()
    {
        return view('livewire.web.blog.show');
    }
}