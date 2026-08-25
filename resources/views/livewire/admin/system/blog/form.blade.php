<?php

use App\Models\AdminLog;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Media;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use App\Actions\Admin\BlogPostsAction;

new #[Layout('layouts.admin')] class extends Component 
{
    
    public ?BlogPost $post = null;

    public string $title = '';
    public string $slug = '';
    public string $excerpt = '';
    public string $body = '';
    public int|string|null $category_id = null;
    public bool $is_featured = false;
    public string $status = 'draft';
    
    public ?int $cover_media_id = null;
    public ?string $coverPreviewUrl = null;

    public bool $showCategoryModal = false;
    public string $newCategoryName = '';

    public string $backUrl = '';

    public array $statuses = [
        'draft' => 'Черновик',
        'published' => 'Опубликована',
        'archived' => 'В архиве'
    ];

    public function mount(?BlogPost $post = null): void
    {
        $previousUrl = url()->previous();
        $currentUrl = url()->current();
        $this->backUrl = $previousUrl && $previousUrl !== $currentUrl ? $previousUrl : route('admin.system.blog.index');

        if ($post && $post->exists) {
            $this->post = $post;
            $this->title = $post->title;
            $this->slug = $post->slug;
            $this->excerpt = $post->excerpt ?? '';
            $this->body = $post->body ?? '';
            $this->category_id = $post->category_id ? (string) $post->category_id : null;
            $this->is_featured = $post->is_featured;
            $this->status = $post->status;
            
            $this->cover_media_id = $post->cover_media_id;
            $this->coverPreviewUrl = $post->cover?->getVariantUrl('sm'); 
        }
    }

   #[Computed]
    public function categories(): array
    {
        return BlogCategory::active()->ordered()
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->toArray();
    }

    public function updatedTitle(string $value): void
    {
        if (!$this->post || !$this->post->exists) {
            $baseSlug = Str::slug($value);
            $this->slug = $this->generateUniqueSlug($baseSlug);
        }
    }

    protected function generateUniqueSlug(string $slug): string
    {
        $originalSlug = $slug;
        $counter = 1;
        while (BlogPost::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        return $slug;
    }

    #[On('media-selected')] 
    public function onMediaSelected(int $mediaId, string $collection): void
    {
        if ($collection === 'post') {
            $media = Media::find($mediaId);
            $this->cover_media_id = $mediaId;
            $this->coverPreviewUrl = $media?->getVariantUrl('sm') ?? '';
        }
    }

    public function removeCover(): void
    {
        $this->cover_media_id = null;
        $this->coverPreviewUrl = null;
    }

    public function createCategory(): void
    {
        try {
            $this->validate([
                'newCategoryName' => 'required|string|max:255|unique:blog_categories,name',
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка валидации рубрики!');
            throw $e;
        }

        $categoryName = $this->newCategoryName;

        $category = BlogCategory::create([
            'name' => $categoryName,
            'slug' => Str::slug($categoryName),
            'sort_order' => (BlogCategory::max('sort_order') ?? 0) + 1,
        ]);

        $this->category_id = (string) $category->id;

        $this->showCategoryModal = false;
        $this->newCategoryName = '';
        
        unset($this->categories);

        $this->dispatch('show-toast', type: 'success', message: 'Рубрика "' . $categoryName . '" создана и выбрана!');
    }

    public function deleteCategory(int $id): void
    {
        $category = BlogCategory::find($id);
        if (!$category) return;

        // Защита: если удаляем рубрику, которая сейчас выбрана — сбрасываем выбор
        if ((string)$this->category_id === (string)$id) {
            $this->category_id = null;
        }

        // Отвязываем посты от удаляемой рубрики (чтобы не падали ошибки FK)
        // Они просто станут "Без рубрики"
        BlogPost::where('category_id', $id)->update(['category_id' => null]);
        
        $category->delete();
        
        // Сбрасываем кэш списка рубрик, чтобы они исчезли из селекта
        unset($this->categories);

        $this->dispatch('show-toast', type: 'success', message: 'Рубрика "' . $category->name . '" удалена!');
    }

    protected function rules(): array
    {
        $slugRule = 'required|alpha_dash|unique:blog_posts,slug';
        if ($this->post && $this->post->exists) {
            $slugRule .= ',' . $this->post->id;
        }

        return [
            'title' => 'required|string|max:255',
            'slug' => $slugRule,
            'excerpt' => 'nullable|string|max:500',
            'body' => 'nullable|string',
            'category_id' => 'nullable|exists:blog_categories,id', 
            'status' => 'required|in:draft,published,archived',
            'is_featured' => 'boolean',
            'cover_media_id' => 'nullable|exists:media,id',
        ];
    }

    public function save(BlogPostsAction $action): void
    {
        try {
            $validated = $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка валидации: проверьте выделенные поля.');
            throw $e; // Пробрасываем ошибку дальше, чтобы Livewire подсветил поля красным
        }

        try {
            $validated['cover_media_id'] = $this->cover_media_id;

            if ($this->post && $this->post->exists) {
                $action->updatePost($this->post, $validated);
                $this->dispatch('show-toast', type: 'success', message: 'Пост успешно обновлен!');
            } else {
                $post = $action->createPost($validated);
                $this->dispatch('show-toast', type: 'success', message: 'Пост создан!');
                $this->redirect(route('admin.system.blog.edit', $post), navigate: true);
            }
        } catch (\Exception $e) {
            Log::error('Ошибка при сохранении поста: ' . $e->getMessage(), ['admin_id' => auth()->id()]);
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при сохранении!');
        }
    }
};
?>

<div class="space-y-6 pb-6" x-data="blogForm()">
    <!-- Заголовок и хлебные крошки -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-3">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors mt-1">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <a href="{{ route('admin.system.blog.index') }}" wire:navigate class="hover:text-foreground transition-colors">Блог</a>
                    <x-lucide-chevron-right class="w-4 h-4" />
                    <span>{{ $post && $post->exists ? 'Редактирование' : 'Создание' }}</span>
                </div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-newspaper class="w-6 h-6" />
                    {{ $title ?: 'Новый пост' }}
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button @click="sidebarOpen = !sidebarOpen" variant="outline" size="sm" class="hidden lg:inline-flex">
                <x-lucide-panel-right class="w-4 h-4" />
                <span x-text="sidebarOpen ? 'Скрыть панель' : 'Показать панель'"></span>
            </x-ui.button>
            <x-ui.button wire:click="save" variant="default" size="sm" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save" class="flex items-center gap-2">
                    <x-lucide-save class="w-4 h-4 inline" /> <span>Сохранить</span>
                </span>
                <span wire:loading wire:target="save" class="flex items-center gap-2">
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> <span>Сохранение...</span>
                </span>
            </x-ui.button>
        </div>
    </div>

    <!-- Основная сетка -->
    <div class="grid grid-cols-1 gap-6 items-stretch" :class="sidebarOpen ? 'lg:grid-cols-3' : 'lg:grid-cols-1'">

        <!-- ЛЕВАЯ КОЛОНКА -->
        <div class="flex" :class="sidebarOpen ? 'lg:col-span-2' : 'lg:col-span-1'">
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm flex flex-col gap-4 w-full min-h-[500px]">
                
                <div class="flex flex-col gap-2">
                    <x-ui.label for="title">Заголовок поста</x-ui.label>
                    <x-ui.input id="title" wire:model="title" placeholder="Введите заголовок" />
                    @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- TINYMCE РЕДАКТОР -->
                <div class="flex flex-col gap-2 flex-1 min-h-0 relative">
                    <x-ui.label>Текст поста</x-ui.label>
                    <div x-show="!isEditorLoaded" x-cloak class="absolute inset-0 top-8 flex items-center justify-center bg-card border border-border rounded-md z-10">
                        <div class="flex flex-col items-center gap-3 text-muted-foreground">
                            <x-lucide-loader-2 class="w-8 h-8 animate-spin text-primary" />
                            <span class="text-sm font-medium">Загрузка редактора...</span>
                        </div>
                    </div>
                    <div wire:ignore class="tinymce-wrapper flex-1 min-h-0 flex flex-col" x-show="isEditorLoaded" x-cloak>
                        <textarea id="tinyMceBody" placeholder="Введите текст...">{{ $body }}</textarea>
                    </div>
                    @error('body') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- ПРАВАЯ КОЛОНКА -->
        <div class="space-y-6 lg:col-span-1" x-show="sidebarOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" x-cloak>
            
            <!-- 1. КРАТКОЕ ОПИСАНИЕ -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm">
                <h3 class="text-sm font-medium mb-4 flex items-center gap-2 text-muted-foreground uppercase tracking-wide">
                    <x-lucide-align-left class="w-4 h-4" /> Краткое описание
                </h3>
                <div class="flex flex-col gap-2">
                    <x-ui.textarea id="excerpt" wire:model="excerpt" rows="3" placeholder="Краткий текст для списка статей и SEO..." class="resize-none text-sm" />
                    @error('excerpt') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted-foreground">Если пусто — система возьмет первые строки из основного текста.</p>
                </div>
            </div>

            <!-- 2. ОБЛОЖКА ПОСТА -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm">
                <h3 class="text-sm font-medium mb-4 flex items-center gap-2 text-muted-foreground uppercase tracking-wide">
                    <x-lucide-image class="w-4 h-4" /> Обложка поста
                </h3>

                <div class="min-h-[10rem] flex flex-col items-center justify-center gap-3 border border-dashed border-border rounded-lg p-3 bg-muted/10 text-center">
                    
                    @if($coverPreviewUrl)
                        <div class="flex flex-col items-center gap-3 w-full">
                            <div class="w-full aspect-[16/9] bg-background rounded-lg overflow-hidden border border-border shrink-0">
                                <img src="{{ $coverPreviewUrl }}" class="w-full h-full object-cover" alt="Cover Preview">
                            </div>
                            <div class="grid grid-cols-2 gap-2 w-full">
                                <x-ui.button type="button" wire:click="$dispatch('open-media-manager', { collection: 'post' })" variant="outline" size="sm" class="w-full gap-1.5">
                                    <x-lucide-refresh-cw class="w-3.5 h-3.5" /> Заменить
                                </x-ui.button>
                                <x-ui.button type="button" wire:click="removeCover" variant="destructive" size="sm" class="w-full gap-1.5">
                                    <x-lucide-trash-2 class="w-3.5 h-3.5" /> Удалить
                                </x-ui.button>
                            </div>
                        </div>
                    @else
                        <x-lucide-image-plus class="w-8 h-8 text-muted-foreground/50" />
                        <x-ui.button type="button" wire:click="$dispatch('open-media-manager', { collection: 'post' })" variant="secondary" size="sm" class="gap-1.5">
                            <x-lucide-folder-open class="w-3.5 h-3.5" /> Выбрать из хранилища
                        </x-ui.button>
                    @endif
                </div>
            </div>

            <!-- 3. НАСТРОЙКИ ПУБЛИКАЦИИ -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm">
                <h3 class="text-sm font-medium mb-4 flex items-center gap-2 text-muted-foreground uppercase tracking-wide">
                    <x-lucide-settings class="w-4 h-4" /> Настройки
                </h3>

                <div class="flex flex-col gap-2 mb-4">
                    <x-ui.label for="slug" class="text-xs">URL (Slug)</x-ui.label>
                    <x-ui.input id="slug" wire:model="slug" placeholder="url-posta" class="flex-1 text-sm" />
                    @error('slug') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- РУБРИКА -->
                <div class="flex flex-col gap-2 mb-4">
                    <x-ui.label for="category_id" class="text-xs">Рубрика</x-ui.label>
                    <div class="flex gap-2 items-center">
                        <x-ui.select wire:key="category-select-{{ count($this->categories) }}" wire:model="category_id" id="category_id" class="flex-1">
                            <x-ui.select-trigger class="w-full">
                                <x-ui.select-value placeholder="- Без рубрики -" />
                            </x-ui.select-trigger>
                            <x-ui.select-content>
                                @foreach($this->categories as $id => $name)
                                    <x-ui.select-item wire:key="cat-item-{{ $id }}" value="{{ $id }}">{{ $name }}</x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>

                       <!-- КНОПКА ДОБАВЛЕНИЯ -->
                        <x-ui.button type="button" @click="$wire.showCategoryModal = true" class="shrink-0 p-2 rounded-md border border-border hover:bg-accent hover:text-accent-foreground transition-colors" title="Быстрое создание рубрики">
                            <x-lucide-plus class="w-4 h-4" />
                        </x-ui.button>
                    </div>
                    @error('category_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div> 

                <!-- СТАТУС -->
                <div class="flex flex-col gap-2 mb-4">
                    <x-ui.label for="status" class="text-xs">Статус</x-ui.label>
                    <x-ui.select wire:model="status" id="status">
                        <x-ui.select-trigger class="w-full">
                            <x-ui.select-value />
                        </x-ui.select-trigger>
                        <x-ui.select-content>
                            @foreach($statuses as $value => $label)
                                <x-ui.select-item wire:key="status-item-{{ $value }}" value="{{ $value }}">{{ $label }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>
                    @error('status') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- ЗАКРЕПЛЕННЫЙ ПОСТ (С ИКОНКОЙ PIN) -->
                <div class="flex items-center justify-between p-3 rounded-md border border-border mb-4 bg-muted/30">
                    <div class="pr-4 flex items-center gap-2">
                        <!-- Иконка Pin -->
                        <x-lucide-pin class="w-5 h-5 text-muted-foreground" /> 
                        <div class="flex flex-col">
                            <p class="text-sm font-medium leading-tight">Закрепленный пост</p>
                            <p class="text-xs text-muted-foreground leading-tight">Выводить первым в списке</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_featured" class="sr-only peer" />
                        <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                    </label>
                </div>

                @if ($post && $post->exists)
                    <div class="text-xs text-muted-foreground space-y-1 border-t border-border pt-4">
                        <div class="flex justify-between"><span>Создано:</span><span class="text-foreground">{{ $post->created_at->format('d.m.Y H:i') }}</span></div>
                        <div class="flex justify-between"><span>Изменено:</span><span class="text-foreground">{{ $post->updated_at->format('d.m.Y H:i') }}</span></div>
                        <div class="flex justify-between"><span>Просмотров:</span><span class="text-foreground">{{ $post->views_count }}</span></div>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- МОДАЛКА УПРАВЛЕНИЯ РУБРИКАМИ -->
        <div x-data="{}" x-show="$wire.showCategoryModal" 
             x-cloak 
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             @click.self="$wire.showCategoryModal = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            
            <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-4 overflow-hidden">
                <!-- Шапка -->
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <h2 class="text-lg font-semibold">Управление рубриками</h2>
                    <x-ui.button variant="ghost" size="icon-sm" @click="$wire.showCategoryModal = false">
                        <x-lucide-x class="w-5 h-5" />
                    </x-ui.button>
                </div>

                <!-- Тело модалки -->
                <div class="p-6 space-y-4">
                    
                    <!-- Список существующих рубрик -->
                    <div class="max-h-60 overflow-y-auto pr-2 space-y-1 border-b border-border pb-4 little-scroll">
                        @forelse($this->categories as $id => $name)
                            <div class="flex items-center justify-between p-2 rounded-md hover:bg-muted/50 group">
                                <span class="text-sm font-medium">{{ $name }}</span>
                                <button wire:click="deleteCategory({{ $id }})" wire:confirm="Удалить рубрику? Посты не удалятся, просто станут 'Без рубрики'." class="opacity-0 group-hover:opacity-100 text-destructive hover:bg-destructive/10 p-1.5 rounded-md transition-all" title="Удалить">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            </div>
                        @empty
                            <p class="text-center text-sm text-muted-foreground py-4">Пока нет ни одной рубрики</p>
                        @endforelse
                    </div>

                    <!-- Форма создания новой -->
                    <div class="flex flex-col gap-2">
                        <x-ui.label for="newCategoryName">Создать новую</x-ui.label>
                        <div class="flex gap-2">
                            <x-ui.input id="newCategoryName" wire:model.live="newCategoryName" placeholder="Например: Психология" class="flex-1" wire:target="createCategory" />
                            
                            <x-ui.button wire:click="createCategory" variant="default" size="sm" wire:loading.attr="disabled" wire:target="createCategory">
                                <span wire:loading.remove wire:target="createCategory" class="flex items-center gap-1"><x-lucide-plus class="w-4 h-4" /> Добавить</span>
                                <span wire:loading wire:target="createCategory" class="flex items-center gap-2"><x-lucide-loader-2 class="w-4 h-4 animate-spin" /></span>
                            </x-ui.button>
                        </div>
                        @error('newCategoryName') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Подключаем TinyMCE и наш внешний файл конфигурации -->
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js"></script>
    <script src="{{ asset('js/tinymce.config.js') }}"></script>

    <script>
        window.blogForm = function () {
            return {
                sidebarOpen: true,
                themeObserver: null,
                currentTheme: 'light',
                isEditorLoaded: false,
                textareaElement: null,
                typingTimer: null,

                init() {
                    this.$nextTick(() => {
                        this.textareaElement = document.getElementById('tinyMceBody');
                        if (this.textareaElement) {
                            this.waitForTinyMCE();
                            this.setupThemeWatcher();
                        } else {
                            // ФИКС: Если DOM еще не готов, ждем 100мс и пробуем снова, пока не найдет textarea
                            setTimeout(() => this.init(), 100);
                        }
                    });
                },

                waitForTinyMCE() {
                    if (typeof tinymce !== 'undefined' && this.textareaElement) {
                        this.initTinyMCE();
                    } else {
                        setTimeout(() => this.waitForTinyMCE(), 100);
                    }
                },

                getTheme() {
                    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                },

                getCssVar(name) {
                    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
                },

                initTinyMCE() {
                    if (typeof tinymce === 'undefined' || !this.textareaElement) return;
                    
                    // ФИКС: Жестко убиваем старые инстансы, если они зависли, чтобы не было гонок
                    if (tinymce.get('tinyMceBody')) {
                        tinymce.get('tinyMceBody').remove();
                    }

                    this.currentTheme = this.getTheme();
                    const isDark = this.currentTheme === 'dark';

                    const bgColor = this.getCssVar('--background');
                    const textColor = this.getCssVar('--foreground');
                    const borderColor = this.getCssVar('--border');
                    const mutedColor = this.getCssVar('--muted-foreground');
                    const mutedBgColor = this.getCssVar('--muted');

                    // Достаем настройки из внешнего файла
                    const config = window.getTinyMceConfig(isDark, textColor, bgColor, borderColor, mutedColor, mutedBgColor);
                    
                    // Добавляем селектор и коллбэки
                    config.selector = '#tinyMceBody';
                    config.setup = (editor) => {
                        editor.on('init', () => {
                            this.isEditorLoaded = true; 
                        });

                        editor.on('input change keyup undo redo SetContent', () => {
                            clearTimeout(this.typingTimer);
                            this.typingTimer = setTimeout(() => {
                                // ФИКС: Напрямую пушим в Livewire, минуя баги с textarea. 
                                // Добавили ?? '', чтобы не уронить Livewire, если getContent вдруг вернет null
                                this.$wire.set('body', editor.getContent() ?? '');
                            }, 500);
                        });
                    };

                    tinymce.init(config);
                },

                destroyTinyMCE() {
                    if (typeof tinymce !== 'undefined' && tinymce.get('tinyMceBody')) {
                        this.isEditorLoaded = false; 
                        clearTimeout(this.typingTimer);
                        tinymce.get('tinyMceBody').remove();
                    }
                },

                setupThemeWatcher() {
                    this.themeObserver = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.attributeName === 'class') {
                                const newTheme = this.getTheme();
                                if (newTheme !== this.currentTheme) {
                                    this.destroyTinyMCE();
                                    setTimeout(() => this.initTinyMCE(), 50);
                                }
                            }
                        });
                    });
                    this.themeObserver.observe(document.documentElement, { attributes: true });
                },

                destroy() {
                    if (this.themeObserver) {
                        this.themeObserver.disconnect();
                        this.themeObserver = null;
                    }
                    this.destroyTinyMCE();
                }
            };
        };
    </script>
    
    <livewire:admin.media-manager />
</div>