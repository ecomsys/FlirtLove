<?php

use App\Models\AdminLog;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public string $group = 'general';
    public array $settings = [];

    public function mount(): void
    {
        $this->group = session('admin_settings_group', 'general');
        $this->loadSettings();
    }

    /**
     * Загрузка настроек текущей группы из БД с автокастингом для UI.
     */
    public function loadSettings(): void
    {
        $settings = Setting::where('group', $this->group)->orderBy('id')->get();
        
        $this->settings = $settings->mapWithKeys(function ($item) {
            $value = $item->value;
            
            // Приводим значения к удобному виду для Livewire форм
            if ($item->type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif ($item->type === 'json' && is_string($value)) {
                $value = json_decode($value, true) ?? [];
            } elseif ($item->type === 'integer') {
                $value = (int) $value;
            }
            
            return [$item->id => [
                'key' => $item->key,
                'value' => $value,
                'type' => $item->type,
                'label' => $item->label,
                'description' => $item->description,
                'options' => $item->options, // Массив для select
                'is_public' => $item->is_public,
            ]];
        })->toArray();
    }

    /**
     * Переключение группы настроек.
     */
    public function setGroup(string $group): void
    {
        $this->group = $group;
        session(['admin_settings_group' => $group]);
        $this->loadSettings();
    }

    /**
     * Сохранение настроек.
     * Конвертирует данные из форм обратно в строки для БД.
     */
    public function save(): void
    {
        try {
            DB::transaction(function () {
                foreach ($this->settings as $id => $data) {
                    $value = $data['value'];
                    
                    // Обратная конвертация для БД
                    if ($data['type'] === 'boolean') {
                        $value = $value ? '1' : '0';
                    } elseif ($data['type'] === 'json' && is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    } elseif ($data['type'] === 'integer') {
                        $value = (string) $value;
                    }
                    
                    Setting::where('id', $id)->update(['value' => $value]);
                }
            });

            // Кэш сбросится автоматически благодаря boot-методу в модели Setting!
            AdminLog::record('setting.update', Setting::first(), auth()->user(), [], ['group' => $this->group]);
            
            $this->dispatch('show-toast', type: 'success', message: 'Настройки сохранены!');
            $this->loadSettings();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сохранения: ' . $e->getMessage());
        }
    }

    /**
     * Отмена несохраненных изменений.
     */
    public function resetSettings(): void
    {
        $this->loadSettings();
        $this->dispatch('show-toast', type: 'info', message: 'Изменения отменены');
    }

    // ============================================
    // ВЫВОД ДАННЫХ
    // ============================================

    /**
     * Динамический список групп из БД (вместо хардкода).
     */
    #[Computed]
    public function groups(): \Illuminate\Support\Collection
    {
        return Setting::select('group')->distinct()->orderBy('group')->pluck('group');
    }

    /**
     * Человекочитаемые названия для групп (для UI).
     * Если группы нет в списке — выводим как есть (с большой буквы).
     */
    #[Computed]
    public function groupLabels(): array
    {
        return [
            'general' => 'Основные',
            'limits' => 'Лимиты',
            'moderation' => 'Модерация',
            'security' => 'Безопасность',
            'finance' => 'Финансы',
            'seo' => 'SEO',
            'social' => 'Соц. сети',
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-settings class="w-6 h-6" />
            Настройки системы
        </h1>
        
        <div class="flex items-center gap-2">
            <x-ui.button wire:click="resetSettings" variant="outline" size="sm">
                <x-lucide-undo class="w-4 h-4" />
                Сбросить изменения
            </x-ui.button>
            
            <x-ui.button wire:click="save" variant="default" size="sm">
                <x-lucide-save class="w-4 h-4" />
                Сохранить
            </x-ui.button>
        </div>
    </div>

    <!-- Группы (Вкладки) -->
    <div class="flex flex-wrap gap-2 border-b border-border pb-4">
        @foreach ($this->groups as $key)
            @php
                $label = $this->groupLabels[$key] ?? ucfirst($key);
            @endphp
            <button 
                wire:click="setGroup('{{ $key }}')"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2
                    {{ $group === $key 
                        ? 'bg-primary text-primary-foreground' 
                        : 'bg-card border border-border hover:bg-accent text-muted-foreground hover:text-foreground' }}"
            >
                @if($key === 'general') <x-lucide-globe class="w-4 h-4" />
                @elseif($key === 'limits') <x-lucide-gauge class="w-4 h-4" />
                @elseif($key === 'moderation') <x-lucide-shield-check class="w-4 h-4" />
                @elseif($key === 'security') <x-lucide-lock class="w-4 h-4" />
                @elseif($key === 'finance') <x-lucide-wallet class="w-4 h-4" />
                @elseif($key === 'seo') <x-lucide-search class="w-4 h-4" />
                @elseif($key === 'social') <x-lucide-share-2 class="w-4 h-4" />
                @else <x-lucide-settings-2 class="w-4 h-4" />
                @endif
                {{ $label }}
            </button>
        @endforeach
    </div>

    <!-- Форма настроек -->
    <div class="bg-card border border-border rounded-lg overflow-hidden">
        <div class="p-6 space-y-6">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                {{ $this->groupLabels[$group] ?? ucfirst($group) }}
                <span class="text-xs text-muted-foreground font-normal">
                    ({{ count($settings) }} настроек)
                </span>
            </h2>

            @if(empty($settings))
                <div class="py-12 text-center text-muted-foreground">
                    <x-lucide-inbox class="w-12 h-12 opacity-30 mx-auto mb-2" />
                    <p>В этой группе пока нет настроек</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($settings as $id => $setting)
                        <div wire:key="setting-{{ $id }}" class="flex items-start gap-4 p-4 bg-muted/10 rounded-lg border border-border hover:border-primary/30 transition-colors">
                            <div class="flex-1 pt-1">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ $setting['label'] ?? $setting['key'] }}
                                    @if($setting['is_public'])
                                        <x-ui.badge variant="outline" size="xs" class="ml-2 text-green-500 border-green-500/30">API</x-ui.badge>
                                    @endif
                                </label>
                                @if(!empty($setting['description']))
                                    <p class="text-xs text-muted-foreground mt-1">{{ $setting['description'] }}</p>
                                @endif
                                <p class="text-xs text-muted-foreground mt-1 opacity-70">
                                    Ключ: <code class="px-1 py-0.5 bg-muted rounded text-[10px]">{{ $setting['key'] }}</code>
                                </p>
                            </div>
                            
                            <div class="w-80">
                                @if($setting['type'] === 'boolean')
                                    <div class="flex items-center pt-1">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input 
                                                type="checkbox" 
                                                wire:model="settings.{{ $id }}.value"
                                                class="sr-only peer"
                                            />
                                            <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                                        </label>
                                    </div>
                                    
                                @elseif($setting['type'] === 'select')
                                    <x-ui.select wire:model="settings.{{ $id }}.value" class="w-full">
                                        <x-ui.select-trigger>
                                            <x-ui.select-value placeholder="Выберите..." />
                                        </x-ui.select-trigger>
                                        <x-ui.select-content>
                                            @foreach($setting['options'] ?? [] as $val => $label)
                                                <x-ui.select-item value="{{ $val }}">{{ $label }}</x-ui.select-item>
                                            @endforeach
                                        </x-ui.select-content>
                                    </x-ui.select>

                                @elseif($setting['type'] === 'textarea')
                                    <x-ui.textarea 
                                        wire:model="settings.{{ $id }}.value"
                                        rows="3"
                                        class="w-full"
                                    />

                                @elseif($setting['type'] === 'json')
                                    <x-ui.textarea 
                                        wire:model="settings.{{ $id }}.value"
                                        rows="4"
                                        class="w-full font-mono text-xs little-scroll"
                                        placeholder="JSON массив..."
                                    />

                                @elseif($setting['type'] === 'integer')
                                    <x-ui.input 
                                        wire:model="settings.{{ $id }}.value"
                                        type="number"
                                        class="w-full"
                                    />

                                @else
                                    <x-ui.input 
                                        wire:model="settings.{{ $id }}.value"
                                        type="text"
                                        class="w-full"
                                    />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Подсказка -->
    <div class="text-xs text-muted-foreground p-4 bg-muted/10 rounded-lg border border-border">
        <p class="flex items-center gap-2">
            <x-lucide-info class="w-4 h-4 shrink-0" />
            <span>
                Изменения вступают в силу сразу после сохранения. Кэш сбрасывается автоматически. 
                Бейдж <span class="text-green-500 font-medium">API</span> означает, что настройка публичная и отдается на фронтенд.
            </span>
        </p>
    </div>
</div>