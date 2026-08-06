<?php

use App\Models\AdminLog;
use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    /** @var Page|null Текущая редактируемая страница (null при создании) */
    public ?Page $page = null;
    
    /** @var string Заголовок страницы (H1) */
    public string $title = '';
    
    /** @var string URL адрес страницы (генерируется из заголовка или вручную) */
    public string $slug = '';
    
    /** @var string HTML контент страницы из редактора TinyMCE */
    public string $body = '';
    
    /** @var string SEO описание для мета-тегов */
    public string $meta_description = '';
    
    /** @var bool Статус публикации (true - опубликована, false - черновик) */
    public bool $is_active = false;
    
    /** @var string URL для кнопки "Назад" (сохраняет предыдущую страницу) */
    public string $backUrl = '';

    /**
     * Инициализация компонента.
     * Определяет URL для возврата и заполняет свойства данными страницы (если редактируем).
     *
     * @param Page|null $page Модель страницы (передается через route model binding)
     * @return void
     */
    public function mount(?Page $page = null): void
    {
        // Запоминаем, откуда мы пришли, один раз при загрузке страницы
        $previousUrl = url()->previous();
        $currentUrl = url()->current();
        
        // Если предыдущий URL не пустой и не равен текущему (чтобы не зацикливаться)
        $this->backUrl = ($previousUrl && $previousUrl !== $currentUrl) 
            ? $previousUrl 
            : route('admin.system.pages.index');

        if ($page && $page->exists) { 
            $this->page = $page;
            $this->title = $page->title;
            $this->slug = $page->slug;
            $this->body = $page->body ?? '';
            $this->meta_description = $page->meta_description ?? '';
            $this->is_active = $page->is_active;
        } else {
            $this->is_active = false; 
        }
    }   

    /**
     * Хук Livewire: срабатывает при изменении заголовка.
     * Автоматически генерирует уникальный slug только при создании новой страницы.
     * Если slug занят, автоматически дописывает -2, -3 и т.д.
     *
     * @param string $value Новое значение заголовка
     * @return void
     */
    public function updatedTitle(string $value): void
    {
        if (!$this->page || !$this->page->exists) {
            $baseSlug = Str::slug($value);
            $this->slug = $this->generateUniqueSlug($baseSlug);
        }
    }

    /**
     * Вспомогательный метод для генерации уникального slug.
     *
     * @param string $slug Базовый slug
     * @return string Уникальный slug
     */
    protected function generateUniqueSlug(string $slug): string
    {
        $originalSlug = $slug;
        $counter = 1;

        // Проверяем, есть ли уже такой slug в базе
        while (Page::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Правила валидации данных.
     * Уникальность slug проверяется с исключением текущей страницы при редактировании.
     *
     * @return array
     */
    protected function rules(): array
    {
        $slugRule = 'required|alpha_dash|unique:pages,slug';
        if ($this->page && $this->page->exists) {
            $slugRule .= ',' . $this->page->id;
        }

        return [
            'title' => 'required|string|max:255',
            'slug' => $slugRule,
            'body' => 'nullable|string',
            'meta_description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Сохранение (создание или обновление) страницы.
     * Включает очистку HTML от XSS, транзакционное логирование и обработку ошибок.
     *
     * @return void
     */
    public function save(): void
    {
        $validated = $this->validate();

        try {
            // 1. Очистка HTML от XSS (Требуется установленный mews/purifier)
            if (class_exists(\Mews\Purifier\Facades\Purifier::class)) {
                $validated['body'] = clean($validated['body']);
            }

            if ($this->page && $this->page->exists) {
                // Получаем оригинальные данные ДО обновления для логирования
                $before = $this->page->getOriginal();
                
                $this->page->update($validated);
                
                $after = $this->page->fresh()->toArray();
                
                // Логируем в админ-логи
                AdminLog::record('page.update', $this->page, auth()->user(), $before, $after);
                
                // Логируем в системные логи Laravel (storage/logs/laravel.log)
                Log::info("Админ обновил страницу", [
                    'page_id' => $this->page->id,
                    'admin_id' => auth()->id(),
                    'admin_email' => auth()->user()->email ?? 'unknown',
                ]);
                
                $this->dispatch('show-toast', type: 'success', message: 'Страница успешно обновлена!');
            } else {
                $page = Page::create($validated);
                
                AdminLog::record('page.create', $page, auth()->user());
                
                Log::info("Админ создал новую страницу", [
                    'page_id' => $page->id,
                    'title' => $page->title,
                    'admin_id' => auth()->id(),
                    'admin_email' => auth()->user()->email ?? 'unknown',
                ]);
                
                $this->dispatch('show-toast', type: 'success', message: 'Страница создана!');
                
                $this->redirect(route('admin.system.pages.edit', $page), navigate: true);
            }
        } catch (\Exception $e) {
            // Если что-то пошло не так (например, база упала), пишем ошибку в логи
            Log::error("Ошибка при сохранении страницы админом: " . $e->getMessage(), [
                'admin_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при сохранении!');
        }
    }
}; 
?>


<div class="space-y-6 pb-6" x-data="pageForm()">
    <!-- Заголовок и хлебные крошки -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-3">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors mt-1">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <a href="{{ route('admin.system.pages.index') }}" wire:navigate class="hover:text-foreground transition-colors">Страницы</a>
                    <x-lucide-chevron-right class="w-4 h-4" />
                    <span>{{ $page && $page->exists ? 'Редактирование' : 'Создание' }}</span>
                </div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-file-text class="w-6 h-6" />
                    {{ $title ?: 'Новая страница' }}
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
                    <x-lucide-save class="w-4 h-4 inline" /> 
                    <span>Сохранить</span>
                </span>
                <span wire:loading wire:target="save" class="flex items-center gap-2">
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> 
                    <span>Сохранение...</span>
                </span>
            </x-ui.button>
        </div>
    </div>

    <!-- Основная сетка -->
    <div class="grid grid-cols-1 gap-6 items-stretch" :class="sidebarOpen ? 'lg:grid-cols-3' : 'lg:grid-cols-1'">
        
        <!-- ЛЕВАЯ КОЛОНКА (Контент) -->
        <div class="flex" :class="sidebarOpen ? 'lg:col-span-2' : 'lg:col-span-1'">
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm flex flex-col gap-4 w-full min-h-[500px]">
                <div class="flex flex-col gap-2">
                    <x-ui.label for="title">Заголовок страницы (H1)</x-ui.label>
                    <x-ui.input id="title" wire:model.live="title" placeholder="Например: Политика конфиденциальности" />
                    @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- TINYMCE РЕДАКТОР -->
                <div class="flex flex-col gap-2 flex-1 min-h-0 relative">
                    <x-ui.label>Контент страницы</x-ui.label>
                    
                    <!-- СПИННЕР ЗАГРУЗКИ -->
                    <div x-show="!isEditorLoaded" x-cloak class="absolute inset-0 top-8 flex items-center justify-center bg-card border border-border rounded-md z-10">
                        <div class="flex flex-col items-center gap-3 text-muted-foreground">
                            <x-lucide-loader-2 class="w-8 h-8 animate-spin text-primary" />
                            <span class="text-sm font-medium">Загрузка редактора...</span>
                        </div>
                    </div>

                    <!-- Редактор -->
                    <div wire:ignore class="tinymce-wrapper flex-1 min-h-0 flex flex-col" x-show="isEditorLoaded" x-cloak>
                        <textarea id="tinyMceBody" wire:model="body" placeholder="Введите текст контента..."></textarea>
                    </div>
                    
                    @error('body') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- ПРАВАЯ КОЛОНКА -->
        <div class="space-y-6 lg:col-span-1" x-show="sidebarOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" x-cloak>
            
            <!-- Настройки -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm">
                <h3 class="text-sm font-medium mb-4 flex items-center gap-2 text-muted-foreground uppercase tracking-wide">
                    <x-lucide-settings class="w-4 h-4" /> Настройки
                </h3>

                <div class="flex flex-col gap-2 mb-4">
                    <x-ui.label for="slug" class="text-xs">URL (Slug)</x-ui.label>
                    <div class="flex items-center gap-2">                       
                        <x-ui.input id="slug" wire:model="slug" placeholder="url-stranitsy" class="flex-1 text-sm" />
                    </div>
                    @error('slug') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-between p-3 rounded-md border border-border mb-4 bg-muted/30">
                    <div class="pr-4">
                        <p class="text-sm font-medium">Статус</p>
                        <p class="text-xs text-muted-foreground">{{ $is_active ? 'Доступна всем' : 'Черновик' }}</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_active" class="sr-only peer" />
                        <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                    </label>
                </div>

                @if($page && $page->exists)
                    <div class="text-xs text-muted-foreground space-y-1 border-t border-border pt-4">
                        <div class="flex justify-between">
                            <span>Создано:</span>
                            <span class="text-foreground">{{ $page->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Изменено:</span>
                            <span class="text-foreground">{{ $page->updated_at->format('d.m.Y H:i') }}</span>
                        </div>
                    </div>
                @endif
            </div>

           <!-- SEO Оптимизация + SERP Preview -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm">
                <h3 class="text-sm font-medium mb-4 flex items-center gap-2 text-muted-foreground uppercase tracking-wide">
                    <x-lucide-search class="w-4 h-4" /> SEO
                </h3>

                <div class="mb-4 p-3 rounded-md border border-border bg-muted/20">
                    <p class="text-xs text-muted-foreground uppercase mb-2">Предпросмотр в поисковике:</p>
                    <div class="text-xl text-blue-700 truncate font-normal leading-tight">{{ $title ?: 'Заголовок страницы' }}</div>
                    <div class="text-sm text-green-700 truncate">{{ config('app.url') }}/{{ $slug ?: 'url-stranitsy' }}</div>
                    <div class="text-sm text-gray-600 line-clamp-2 mt-1">{{ $meta_description ?: 'Краткое описание страницы для поисковой выдачи появится здесь...' }}</div>
                </div>

                <div class="flex flex-col gap-2 mb-4">
                    <div class="flex justify-between items-center">
                        <x-ui.label for="meta_title" class="text-xs">Meta Title</x-ui.label>
                        <!-- Считаем через Alpine: $wire.title -->
                        <span class="text-xs" :class="($wire.title || '').length > 60 ? 'text-destructive font-bold' : 'text-muted-foreground'" x-text="`${($wire.title || '').length} / 60`"></span>
                    </div>
                    <x-ui.input id="meta_title" wire:model.live="title" placeholder="SEO заголовок" />
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex justify-between items-center">
                        <x-ui.label for="meta_description" class="text-xs">Meta Description</x-ui.label>
                        <!-- Считаем через Alpine: $wire.meta_description -->
                        <span class="text-xs" :class="($wire.meta_description || '').length > 160 ? 'text-destructive font-bold' : 'text-muted-foreground'" x-text="`${($wire.meta_description || '').length} / 160`"></span>
                    </div>
                    <x-ui.textarea id="meta_description" wire:model.live="meta_description" rows="4" placeholder="Краткое описание (до 160 символов)" class="resize-none text-sm" />
                    @error('meta_description') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    <style>
        .tinymce-wrapper .tox.tox-tinymce {
            border: 1px solid var(--border) !important;
            border-radius: 6px !important;
            height: 100% !important; 
            display: flex !important;
            flex-direction: column !important;
        }
        .tinymce-wrapper .tox .tox-editor-container {
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .tinymce-wrapper .tox .tox-edit-area {
            flex: 1 !important;
            border-top: none !important;
        }
        .tinymce-wrapper .tox .tox-toolbar-overlord,
        .tinymce-wrapper .tox .tox-toolbar__primary {
            background: var(--card) !important;
            border-bottom: 1px solid var(--border) !important;
        }
        .tinymce-wrapper .tox .tox-tbtn {
            color: var(--muted-foreground) !important;
        }
        .tinymce-wrapper .tox .tox-tbtn svg {
            fill: currentColor !important;
        }
        .tinymce-wrapper .tox .tox-tbtn:hover {
            background: var(--accent) !important;
            color: var(--accent-foreground) !important;
        }
        .tinymce-wrapper .tox .tox-tbtn--enabled,
        .tinymce-wrapper .tox .tox-tbtn--enabled:hover {
            background: var(--primary) !important;
            color: var(--primary-foreground) !important;
        }
        .tinymce-wrapper .tox .tox-tbtn--select span {
            color: var(--foreground) !important;
        }       
    </style>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js"></script>
<script>
    window.pageForm = function () {
        return {
            sidebarOpen: true,
            themeObserver: null,
            currentTheme: 'light',
            isEditorLoaded: false,
            textareaElement: null,
            typingTimer: null, // Переменная для дебаунса (задержки отправки данных)

            init() {
                // Ждем отрисовки DOM перед инициализацией редактора
                this.$nextTick(() => {
                    this.textareaElement = document.getElementById('tinyMceBody');
                    this.waitForTinyMCE();
                    this.setupThemeWatcher();
                });
            },

            // Ждем, пока глобальный объект tinymce будет доступен (нужно для wire:navigate)
            waitForTinyMCE() {
                if (typeof tinymce !== 'undefined' && this.textareaElement) {
                    this.initTinyMCE();
                } else {
                    // Если скрипт еще не загрузился, проверяем снова через 100мс
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
                if (tinymce.get('tinyMceBody')) return;

                this.currentTheme = this.getTheme();
                const isDark = this.currentTheme === 'dark';

                const bgColor = this.getCssVar('--background');
                const textColor = this.getCssVar('--foreground');
                const borderColor = this.getCssVar('--border');
                const mutedColor = this.getCssVar('--muted-foreground');
                const mutedBgColor = this.getCssVar('--muted');

                tinymce.init({
                    selector: '#tinyMceBody',
                    license_key: 'gpl',
                    menubar: false,
                    height: '100%',
                    icons: 'default',
                    plugins: 'lists link table image autolink wordcount code fullscreen quickbars',
                    toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist outdent indent | link table image | code fullscreen',
                    skin: isDark ? 'oxide-dark' : 'oxide',
                    content_css: isDark ? 'dark' : 'default',
                    statusbar: false, 
                    placeholder: '',
                    
                    content_style: `
                        body { 
                            background-color: ${bgColor} !important; 
                            color: ${textColor} !important;
                            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
                            font-size: 14px; 
                            line-height: 1.6; 
                            padding: 16px; 
                            margin: 0 !important;
                        }
                        h1, h2, h3, h4 { color: ${textColor} !important; }
                        h1 { font-size: 24px; font-weight: 700; margin-top: 16px; margin-bottom: 8px; }
                        h2 { font-size: 20px; font-weight: 600; margin-top: 16px; margin-bottom: 8px; }
                        p { margin: 0 0 1rem 0; }
                        a { color: #3b82f6; text-decoration: underline; }
                        blockquote { border-left: 4px solid ${borderColor}; padding-left: 16px; color: ${mutedColor}; font-style: italic; margin: 1rem 0; }
                        pre { background-color: ${mutedBgColor}; color: ${textColor}; padding: 1rem; border-radius: 6px; font-family: monospace; overflow-x: auto; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid ${borderColor}; padding: 8px; }
                    `,
                    
                    setup: (editor) => {
                        editor.on('init', () => {
                            this.isEditorLoaded = true; 
                        });

                        editor.on('input change keyup undo redo SetContent', () => {
                            // ФИКС ПРОИЗВОДИТЕЛЬНОСТИ: Обновляем Livewire не чаще раза в 500мс
                            // Это предотвращает DDoS сервера при быстром наборе текста
                            clearTimeout(this.typingTimer);
                            this.typingTimer = setTimeout(() => {
                                if (this.textareaElement) {
                                    this.textareaElement.value = editor.getContent();
                                    this.textareaElement.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            }, 500);
                        });
                    }
                });
            },

            destroyTinyMCE() {
                if (typeof tinymce !== 'undefined' && tinymce.get('tinyMceBody')) {
                    this.isEditorLoaded = false; 
                    clearTimeout(this.typingTimer); // Сбрасываем таймер при уничтожении
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
</div>