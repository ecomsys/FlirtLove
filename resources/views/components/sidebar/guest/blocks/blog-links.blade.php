@php
    // Тянем 5 последних опубликованных статей.
    // Если статьи нет в базе (например, только что залили проект), виджет просто не отрисуется.
    $articles = \App\Models\BlogPost::where('status', 'published')
                    ->latest()
                    ->limit(5)
                    ->get();
@endphp

@if($articles->isNotEmpty())    
    <!-- Используем divide-y для автоматических горизонтальных полосок между записями -->
     <ul class="divide-y divide-border">
        @foreach($articles as $article)
            <li class="py-2.5 first:pt-0 last:pb-0">
                <a href="{{ route('blog.show', $article) }}" wire:navigate class="hover:text-primary transition-colors line-clamp-2 text-sm">
                    {{ $article->title }}
                </a>
            </li>
        @endforeach
    </ul>

    @if(Route::has('blog.index'))
    <a href="{{ route('blog.index') }}" wire:navigate class="block text-sm text-primary hover:underline pt-2 border-t border-border">
        Все статьи
    </a>
    @endif
@endif