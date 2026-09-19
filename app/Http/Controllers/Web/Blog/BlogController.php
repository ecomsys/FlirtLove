<?php

namespace App\Http\Controllers\Web\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $posts = BlogPost::where('status', 'published')
                    ->latest()
                    ->paginate(9);

        return view('pages.blog.index', [
            'posts' => $posts
        ]);
    }

    public function show(BlogPost $post)
    {
        // Если статья не опубликована, отдаем 404
        if ($post->status !== 'published') {
            abort(404);
        }
        
        // Увеличиваем счетчик просмотров
        $post->incrementViews();

        return view('pages.blog.show', [
            'post' => $post
        ]);
    }
}