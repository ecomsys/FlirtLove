<?php

use App\Models\Setting;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Cache;

new #[Layout('layouts.admin')] class extends Component 
{
    public string $group = 'general';
    public array $settings = [];

    public function mount(): void
    {
        $this->loadSettings();
    }

   public function loadSettings(): void
    {
        $settings = Setting::where('group', $this->group)->get();
        
        foreach ($settings as $setting) {
            if ($setting->type === 'boolean') {
                $setting->value = $setting->value == '1' || $setting->value === true;
            }
            // Если null — ставим дефолт
            if ($setting->value === null && $setting->type !== 'boolean') {
                $defaults = $this->getDefaultSettings();
                $setting->value = $defaults[$setting->key] ?? '';
            }
        }
        
        $this->settings = $settings->mapWithKeys(function ($item) {
            return [$item['id'] => $item->toArray()];
        })->toArray();
    }

    public function setGroup(string $group): void
    {
        $this->group = $group;
        $this->loadSettings();
    }

 
    public function save(): void
    {
        try {
            foreach ($this->settings as $id => $setting) {
                $value = $setting['value'];
                
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                
                Setting::where('id', $id)->update([
                    'value' => (string) $value
                ]);
            }

            Cache::forget('settings');
            
            $this->dispatch('show-toast', 
                type: 'success', 
                message: 'Настройки сохранены!'
            );
            
            $this->loadSettings();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', 
                type: 'error', 
                message: 'Ошибка сохранения: ' . $e->getMessage()
            );
        }
    }

    public function resetSettings(): void
    {
        $this->loadSettings();
        $this->dispatch('show-toast', 
            type: 'info', 
            message: 'Изменения отменены, настройки возвращены к сохраненным'
        );
    }

    public function resetToDefault(): void
    {              
        $defaults = $this->getDefaultSettings();
        $count = 0;

        if (empty($this->settings)) {
            $this->dispatch('show-toast', 
                type: 'info', 
                message: 'Нет настроек для сброса'
            );
            return;
        }
        
        foreach ($this->settings as &$setting) {
            if (isset($defaults[$setting['key']])) {
                $defaultValue = $defaults[$setting['key']];
                
                if ($setting['type'] === 'boolean') {
                    $defaultValue = $defaultValue == '1' || $defaultValue === true;
                }
                
                $setting['value'] = $defaultValue;
                $count++;
            }
        }
        
        foreach ($this->settings as $id => $setting) {
            $value = $setting['value'];
            
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            
            Setting::where('id', $id)->update([
                'value' => (string) $value
            ]);
        }

        if ($count === 0) {
            $this->dispatch('show-toast', 
                type: 'info', 
                message: 'Нет настроек для сброса'
            );
            return;
        }
        
        Cache::forget('settings');
        $this->loadSettings();
        
        if ($count > 0) {
            $this->dispatch('show-toast', 
                type: 'success', 
                message: "Сброшено {$count} настроек к заводским значениям"
            );
        }
    }

    private function getDefaultSettings(): array
    {
        return [
            'site_name' => 'LovePlanet',
            'site_description' => 'Сайт знакомств для серьезных отношений',
            'site_url' => 'https://loveplanet.ru',
            'contact_email' => 'support@loveplanet.ru',
            'max_photos_per_user' => '10',
            'moderation_auto_approve' => '0',
            'require_moderation_for_new_users' => '1',
            'min_password_length' => '8',
            'max_login_attempts' => '5',
            'telegram_url' => 'https://t.me/loveplanet',
            'instagram_url' => 'https://instagram.com/loveplanet',
            'vk_url' => 'https://vk.com/loveplanet',
        ];
    }

    #[Computed]
    public function groups(): array
    {
        return [
            'general' => 'Основные',
            'moderation' => 'Модерация',
            'security' => 'Безопасность',
            'social' => 'Социальные сети',
        ];
    }

    #[Computed]
    public function groupSettings()
    {
        return Setting::where('group', $this->group)->get();
    }
}; ?>

<div x-data class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold">Настройки системы</h1>
        
        <div class="flex items-center gap-2">
            <x-ui.button 
                wire:click="resetSettings" 
                variant="outline" 
                size="sm"
            >
                <x-lucide-undo class="w-4 h-4" />
                Сбросить изменения
            </x-ui.button>
            
            <x-ui.alert-dialog>
                <x-ui.alert-dialog-trigger>
                    <x-ui.button variant="destructive" size="sm">
                        <x-lucide-rotate-ccw class="w-4 h-4" />
                        Сбросить к заводским
                    </x-ui.button>
                </x-ui.alert-dialog-trigger>
                <x-ui.alert-dialog-content>
                    <x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-title>
                            ⚠️ Сброс к заводским настройкам
                        </x-ui.alert-dialog-title>
                        <x-ui.alert-dialog-description>
                            Вы уверены? Все настройки будут сброшены к заводским значениям. 
                            Это действие <strong class="text-destructive">нельзя отменить</strong>.
                        </x-ui.alert-dialog-description>
                    </x-ui.alert-dialog-header>
                    <x-ui.alert-dialog-footer>
                        <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                        <x-ui.alert-dialog-action wire:click="resetToDefault">
                            <x-lucide-rotate-ccw class="w-4 h-4" />
                            Сбросить
                        </x-ui.alert-dialog-action>
                    </x-ui.alert-dialog-footer>
                </x-ui.alert-dialog-content>
            </x-ui.alert-dialog>
            
            <x-ui.button 
                wire:click="save" 
                variant="default" 
                size="sm"
            >
                <x-lucide-save class="w-4 h-4" />
                Сохранить
            </x-ui.button>
        </div>
    </div>

    <!-- Группы -->
    <div class="flex flex-wrap gap-2 border-b border-border pb-4">
        @foreach ($this->groups as $key => $label)
            <button 
                wire:click="setGroup('{{ $key }}')"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                    {{ $group === $key 
                        ? 'bg-primary text-primary-foreground' 
                        : 'bg-card border border-border hover:bg-accent text-muted-foreground hover:text-foreground' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <!-- Форма настроек -->
    <div class="bg-card border border-border rounded-lg overflow-hidden">
        <div class="p-6 space-y-6">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                {{ $this->groups[$group] }}
                <span class="text-xs text-muted-foreground font-normal">
                    ({{ $this->groupSettings->count() }} настроек)
                </span>
            </h2>

            <div class="space-y-4">
                @foreach ($this->groupSettings as $setting)
                    <div wire:key="setting-{{ $setting->id }}" class="flex items-start gap-4 p-4 bg-muted/10 rounded-lg border border-border hover:border-primary/30 transition-colors">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-foreground">
                                {{ $setting->label }}
                            </label>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Ключ: <code class="px-1 py-0.5 bg-muted rounded text-[10px]">{{ $setting->key }}</code>
                            </p>
                        </div>
                        <div class="w-80">
                            @if($setting->type === 'boolean')
                                <div class="flex items-center pt-1">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            wire:model="settings.{{ $setting->id }}.value"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                                    </label>
                                </div>
                            @elseif($setting->type === 'select')
                                <x-ui.select 
                                    wire:model="settings.{{ $setting->id }}.value"
                                    class="w-full"
                                >
                                    <x-ui.select-trigger>
                                        <x-ui.select-value placeholder="Выберите..." />
                                    </x-ui.select-trigger>
                                    <x-ui.select-content>
                                        @foreach($setting->options ?? [] as $option)
                                            <x-ui.select-item value="{{ $option }}">
                                                {{ $option }}
                                            </x-ui.select-item>
                                        @endforeach
                                    </x-ui.select-content>
                                </x-ui.select>
                            @elseif($setting->type === 'textarea')
                                <x-ui.textarea 
                                    wire:model="settings.{{ $setting->id }}.value"
                                    rows="3"
                                    class="w-full"
                                />
                            @else
                                <x-ui.input 
                                    wire:model="settings.{{ $setting->id }}.value"
                                    type="{{ $setting->type }}"
                                    class="w-full"
                                />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Подсказка -->
    <div class="text-xs text-muted-foreground p-4 bg-muted/10 rounded-lg border border-border">
        <p class="flex items-center gap-2">
            <x-lucide-info class="w-4 h-4" />
            <span>
                Изменения вступают в силу сразу после сохранения. 
                <strong>«Сбросить изменения»</strong> — отменяет несохраненные правки. 
                <strong class="text-destructive">«Сбросить к заводским»</strong> — полностью возвращает настройки к значениям по умолчанию.
            </span>
        </p>
    </div>
</div>