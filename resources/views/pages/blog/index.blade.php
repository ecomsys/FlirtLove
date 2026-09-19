<x-layouts.web>
    <div class="space-y-6">
        <div class="bg-card border border-border rounded-xl p-6">
            <h1 class="text-3xl font-bold mb-2">Блог знакомств</h1>
            <p class="text-muted-foreground">Статьи о знакомствах, отношениях и психологии общения.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($posts as $post)                
                <a href="{{ route('blog.show', $post) }}" class="bg-card border border-border rounded-xl overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col">
                    @if($post->cover)
                        <div class="aspect-video bg-muted overflow-hidden">
                            <img src="{{ $post->cover_url }}" alt="{{ $post->title }}" class="w-full h-full object-cover">
                        </div>
                    @endif
                    <div class="p-5 flex-1 flex flex-col">
                        <h2 class="text-lg font-semibold mb-2 line-clamp-2">{{ $post->title }}</h2>
                        <p class="text-sm text-muted-foreground line-clamp-3 flex-1">{{ $post->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($post->body), 150) }}</p>
                        <div class="mt-4 pt-4 border-t border-border flex items-center gap-2 text-xs text-muted-foreground">
                            <x-lucide-calendar class="w-3.5 h-3.5" />
                            <span>{{ $post->created_at->format('d.m.Y') }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full text-center py-12 text-muted-foreground">Статей пока нет.</div>
            @endforelse
        </div>

        <div class="pt-4">
            {{ $posts->links() }}
        </div>
    </div>
</x-layouts.web>