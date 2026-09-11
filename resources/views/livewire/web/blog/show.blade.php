<div class="space-y-6">
    <div class="bg-card border border-border rounded-xl overflow-hidden">
        @if($this->post->cover)
        <div class="aspect-[21/9] bg-muted">
            <img src="{{ $this->post->cover_url }}" alt="{{ $this->post->title }}" class="w-full h-full object-cover">
        </div>
        @endif
        
        <div class="p-6 md:p-10">
            <div class="flex items-center gap-3 text-xs text-muted-foreground mb-4">
                <a href="{{ route('blog.index') }}" wire:navigate class="hover:text-primary transition-colors">← Назад к блогу</a>
                <span>•</span>
                <span>{{ $this->post->created_at->format('d F Y') }}</span>
            </div>
            
            <h1 class="text-3xl md:text-4xl font-bold mb-6">{{ $this->post->title }}</h1>
            
            <div class="prose dark:prose-invert max-w-none text-foreground/90">
                {!! $this->post->body !!}
            </div>
        </div>
    </div>
</div>