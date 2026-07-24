@props([
    'title' => 'Подтверждение',
    'message' => 'Вы уверены?',
    'confirmText' => 'Подтвердить',
    'cancelText' => 'Отмена',
    'variant' => 'destructive', // destructive | primary | warning
])

<div 
    x-data="{
        open: false,
        title: '{{ $title }}',
        message: '{{ $message }}',
        confirmText: '{{ $confirmText }}',
        cancelText: '{{ $cancelText }}',
        variant: '{{ $variant }}',
        onConfirm: null,
        onCancel: null,
        
        show(options) {
            this.title = options.title || '{{ $title }}';
            this.message = options.message || '{{ $message }}';
            this.confirmText = options.confirmText || '{{ $confirmText }}';
            this.cancelText = options.cancelText || '{{ $cancelText }}';
            this.variant = options.variant || '{{ $variant }}';
            this.onConfirm = options.onConfirm || null;
            this.onCancel = options.onCancel || null;
            this.open = true;
        },
        
        close() {
            this.open = false;
        },
        
        confirm() {
            if (this.onConfirm && typeof this.onConfirm === 'function') {
                this.onConfirm();
            }
            this.close();
        },
        
        cancel() {
            if (this.onCancel && typeof this.onCancel === 'function') {
                this.onCancel();
            }
            this.close();
        }
    }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    @keydown.escape.window="close()"
>
    <!-- Оверлей для закрытия по клику вне -->
    <div class="absolute inset-0" @click="close()"></div>
    
    <!-- Модальное окно -->
    <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-4 overflow-hidden">
        <!-- Заголовок -->
        <div class="flex items-center gap-3 p-4 border-b border-border">
            <div class="shrink-0">
                <div x-show="variant === 'destructive'" class="w-8 h-8 rounded-full bg-destructive/10 flex items-center justify-center text-destructive">
                    <x-lucide-alert-triangle class="w-5 h-5" />
                </div>
                <div x-show="variant === 'warning'" class="w-8 h-8 rounded-full bg-yellow-500/10 flex items-center justify-center text-yellow-600">
                    <x-lucide-alert-circle class="w-5 h-5" />
                </div>
                <div x-show="variant === 'primary'" class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                    <x-lucide-info class="w-5 h-5" />
                </div>
            </div>
            <h3 class="text-lg font-semibold" x-text="title"></h3>
        </div>
        
        <!-- Содержимое -->
        <div class="p-6">
            <p class="text-sm text-muted-foreground" x-text="message"></p>
        </div>
        
        <!-- Кнопки -->
        <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
            <x-ui.button 
                @click="cancel()"
                variant="outline"
                size="sm"
                x-text="cancelText"
            />
            <x-ui.button 
                @click="confirm()"
                :variant="$variant === 'destructive' ? 'destructive' : 'default'"
                size="sm"
                x-text="confirmText"
            />
        </div>
    </div>
</div>

<!-- Стили для x-cloak -->
<style>
    [x-cloak] { display: none !important; }
</style>