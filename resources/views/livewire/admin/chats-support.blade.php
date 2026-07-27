<?php

use App\Models\Chat;
use App\Models\Message;
use App\Models\ChatParticipant;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

/**
 * Компонент админки: Чат поддержки (Helpdesk).
 * Отвечает за переписку администрации с пользователями.
 * Поддерживает шаблонные ответы, поиск и авто-открытие непрочитанных тикетов.
 */
new #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    /** @var int|null ID активного чата (правая панель) */
    public ?int $activeChatId = null;
    
    /** @var string Текст набираемого сообщения */
    public string $messageBody = '';
    
    /** @var array История сообщений активного чата */
    public array $messages = [];
    
    /** @var string Поисковый запрос по имени пользователя (левая панель) */
    public string $search = '';

    /**
     * Категории и шаблоны ответов для контекстного меню.
     * @var array
     */
    public array $templateCategories = [
        [
            'title' => '--- Приветствия и общие вопросы ---',
            'templates' => [
                ['title' => 'Приветствие', 'body' => "Здравствуйте! Я представитель службы поддержки. Чем могу помочь вам сегодня?"],
                ['title' => 'Запрос деталей проблемы', 'body' => "Спасибо за обращение! Уточните, пожалуйста, детали проблемы (время, ID пользователя или скриншот), чтобы мы могли быстрее вам помочь."],
            ]
        ],
        [
            'title' => '--- Модерация и нарушения ---',
            'templates' => [
                ['title' => 'Предупреждение: Спам', 'body' => "Здравствуйте!\n\nВаш аккаунт получил предупреждение за нарушение правил сайта (рассылка спама в личных сообщениях). Пожалуйста, ознакомьтесь с правилами сообщества. Повторные нарушения приведут к блокировке аккаунта.\n\nС уважением, администрация."],
                ['title' => 'Бан: Мошенничество', 'body' => "Здравствуйте!\n\nВаш аккаунт был заблокирован на основании жалоб пользователей о мошенничестве. В соответствии с правилами сервиса, блокировка является бессрочной.\n\nЕсли вы считаете это ошибкой, приложите доказательства."],
                ['title' => 'Отклонение фото', 'body' => "Здравствуйте!\n\nЗагруженное вами фото было отклонено модератором (нарушение правил, чужое лицо или низкое качество). Пожалуйста, загрузите новую фотографию, чтобы продолжить пользоваться сервисом без ограничений."],
                ['title' => 'Предупреждение: Оскорбления', 'body' => "Здравствуйте!\n\nПоступили жалобы на оскорбительное поведение с вашей стороны. Напоминаем, что в нашем сервисе запрещены ругань и унижения. Пожалуйста, будьте взаимовежливы."],
            ]
        ],
        [
            'title' => '--- Подписки и платежи ---',
            'templates' => [
                ['title' => 'Инфо о Premium', 'body' => "Здравствуйте!\n\nДля безлимитной переписки и расширенных фильтров поиска оформите подписку Premium в разделе «Настройки» -> «Подписка». Если у вас возникли проблемы с оплатой, дайте знать, мы поможем!"],
                ['title' => 'Ошибка оплаты', 'body' => "Здравствуйте!\n\nПлатеж не прошел. Пожалуйста, проверьте данные карты и лимиты, либо попробуйте другую карту. Если средства списались, но Premium не активировался, пришлите чек об оплате."],
                ['title' => 'Отказ в возврате', 'body' => "Здравствуйте!\n\nСогласно правилам сервиса, возврат средств за использованный период подписки не предусмотрен. Полный текст пользовательского соглашения доступен в настройках профиля."],
            ]
        ],
        [
            'title' => '--- Технические проблемы ---',
            'templates' => [
                ['title' => 'Проблема с геолокацией', 'body' => "Здравствуйте!\n\nВаши координаты были обновлены администратором. Перезайдите в приложение, чтобы обновить список анкет поблизости."],
                ['title' => 'Ошибка входа', 'body' => "Здравствуйте!\n\nПопробуйте очистить кэш браузера/приложения или восстановить пароль по кнопке «Забыли пароль?». Если не поможет, напишите нам ваш ID."],
            ]
        ],
        [
            'title' => '--- Закрытие обращения ---',
            'templates' => [
                ['title' => 'Проблема решена', 'body' => "Рады были помочь! Если возникнут еще вопросы, обращайтесь. Хорошего дня и успехов в поисках!"],
                ['title' => 'Закрытие тикета', 'body' => "Мы закрываем данное обращение в связи с решением проблемы. Если вопрос вернется, создайте новый тикет. Спасибо за использование нашего сервиса!"],
            ]
        ],
    ];

    /**
     * Вычисляемое свойство: преобразует дерево категорий в плоский массив
     * с глобальными индексами для быстрого поиска в Alpine.js.
     */
    #[Computed]
    public function flatTemplates(): array
    {
        $flat = [];
        foreach ($this->templateCategories as $category) {
            foreach ($category['templates'] as $template) {
                $flat[] = [
                    'category' => $category['title'],
                    'title' => $template['title'],
                    'body' => $template['body'],
                    'index' => count($flat)
                ];
            }
        }
        return $flat;
    }

    /**
     * Вставить выбранный шаблон в поле ввода сообщения.
     * 
     * @param int $index Глобальный индекс шаблона в массиве flatTemplates
     */
    public function setTemplate(int $index): void
    {
        $this->messageBody = $this->flatTemplates[$index]['body'] ?? '';
        $this->dispatch('focus-message-input');
    }

    /**
     * Инициализация компонента.
     * Если передан user_id (переход из профиля) — открывает чат с ним.
     * Если нет — авто-открывает первый непрочитанный тикет (или последний по дате).
     * 
     * @param int|null $user_id
     */
    public function mount($user_id = null): void
    {
        if ($user_id) {
            $admin = auth()->user();
            $user = \App\Models\User::find($user_id);
            if ($user) {
                $chat = Chat::getOrCreateSupportChat($admin, $user);
                $this->selectChat($chat->id);
            }
        } else {
            // Авто-открытие: Ищем первый непрочитанный тикет
            $adminId = auth()->id();
            $unreadChat = Chat::where('type', 'support')
                ->where('user1_id', $adminId)
                ->whereHas('participants', fn($q) => $q->where('user_id', $adminId)->where('unread_count', '>', 0))
                ->latest('last_message_at')
                ->first();

            if ($unreadChat) {
                $this->selectChat($unreadChat->id);
            } else {
                // Если непрочитанных нет, открываем просто последний тикет
                $latestChat = Chat::where('type', 'support')
                    ->where('user1_id', $adminId)
                    ->latest('last_message_at')
                    ->first();
                if ($latestChat) {
                    $this->selectChat($latestChat->id);
                }
            }
        }
    }

    /**
     * Выбор чата для просмотра в правой панели.
     * Сбрасывает черновик сообщения и счетчик непрочитанных.
     * 
     * @param int $chatId
     */
    public function selectChat(int $chatId): void
    {
        $this->activeChatId = $chatId;
        $this->messageBody = '';
        
        // Сбрасываем счетчик непрочитанных для админа
        ChatParticipant::where('chat_id', $chatId)
            ->where('user_id', auth()->id())
            ->update(['unread_count' => 0]);
            
        $this->loadMessages();
    }

    /**
     * Загрузка истории сообщений для активного чата.
     */
    public function loadMessages(): void
    {
        if (!$this->activeChatId) return;

        $this->messages = Message::where('chat_id', $this->activeChatId)
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Отправка сообщения от имени админа.
     * Обернуто в транзакцию. Инкрементит счетчик непрочитанных у юзера.
     */
    public function sendMessage(): void
    {
        $this->validate(['messageBody' => 'required|string|max:2000']);

        $chat = Chat::find($this->activeChatId);
        if (!$chat) return;

        DB::transaction(function () use ($chat) {
            Message::create([
                'chat_id' => $this->activeChatId,
                'sender_id' => auth()->id(),
                'type' => 'text',
                'body' => $this->messageBody,
            ]);

            $chat->update(['last_message_at' => now()]);

            ChatParticipant::where('chat_id', $this->activeChatId)
                ->where('user_id', '!=', auth()->id())
                ->increment('unread_count');
        });

        $this->messageBody = '';
        $this->loadMessages();
        $this->dispatch('show-toast', type: 'success', message: "Сообщение отправлено!");
    }

    /**
     * Полная очистка истории переписки в чате.
     * 
     * @param int $chatId
     */
    public function clearChat(int $chatId): void
    {
        DB::transaction(function () use ($chatId) {
            Message::where('chat_id', $chatId)->delete();
            Chat::where('id', $chatId)->update(['last_message_at' => null]);
            ChatParticipant::where('chat_id', $chatId)->update(['unread_count' => 0]);
        });

        $this->messages = [];
        $this->dispatch('show-toast', type: 'success', message: 'История чата очищена.');
    }

    /**
     * Вычисляемое свойство: Список тикетов (левая панель) с пагинацией.
     */
    #[Computed]
    public function chats()
    {
        $adminId = auth()->id();
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return Chat::where('type', 'support')
            ->where('user1_id', $adminId)
            ->whereHas('user2', fn($q) => $q->where('name', $operator, "%{$this->search}%"))
            ->with(['user2.photos', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->paginate(15);
    }

    /**
     * Вычисляемое свойство: Статистика для бейджей в заголовке страницы.
     */
    #[Computed]
    public function stats(): array
    {
        $adminId = auth()->id();
        
        return [
            'total' => Chat::where('type', 'support')->where('user1_id', $adminId)->count(),
            'today' => Chat::where('type', 'support')->where('user1_id', $adminId)
                        ->whereDate('last_message_at', today())->count(),
        ];
    }
};
?>

<div class="space-y-6">
    <h1 class="text-2xl font-semibold flex items-center gap-2 flex-wrap">
        <x-lucide-life-buoy class="w-6 h-6" />
        Чат поддержки
        <x-ui.badge variant="default" size="sm">{{ $this->stats['total'] }} всего</x-ui.badge>
        @if($this->stats['today'] > 0)
            <x-ui.badge variant="success" size="sm">{{ $this->stats['today'] }} сегодня</x-ui.badge>
        @endif
    </h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 bg-card border border-border rounded-lg p-4 min-h-[calc(100vh-14rem)]">
        
        <!-- Левая панель: Тикеты (Автопроверка новых сообщений раз в 10 сек) -->        
        <div wire:poll.10s class="lg:col-span-1 border-r border-border pr-4 flex flex-col h-[calc(100vh-14rem)]">
            
            <!-- Поиск (не скроллится) -->
            <div class="relative mb-4 shrink-0">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск юзера..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>

            <!--  Внутренний скроллируемый блок со списком -->
            <div class="flex-1 min-h-0 overflow-y-auto space-y-2 little-scroll pr-1">
                @forelse ($this->chats as $chat)
                    @php $user = $chat->user2; $lastMsg = $chat->messages->first(); @endphp
                    <div wire:click="selectChat({{ $chat->id }})" 
                         class="p-3 rounded-lg cursor-pointer transition-colors {{ $this->activeChatId === $chat->id ? 'bg-primary/10 border border-primary/30' : 'bg-message/75 hover:bg-message border border-transparent' }}"
                         wire:key="sup-chat-{{ $chat->id }}">
                        <div class="flex items-center gap-3">
                            <x-avatar src="{{ $user?->avatar_url }}" name="{{ $user?->name }}" size="sm" userId="{{ $user?->id }}" showStatus="true" />
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium text-sm truncate">{{ $user?->name }}</span>
                                    @if($chat->last_message_at)
                                        <span class="text-[10px] text-muted-foreground ml-2">{{ $chat->last_message_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-muted-foreground truncate mt-0.5">
                                    @if($lastMsg) {{ $lastMsg->body }} @else <span class="italic">Нет сообщений</span> @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-muted-foreground text-sm" wire:key="empty-tickets">
                        <p>Тикетов нет</p>
                    </div>
                @endforelse
            </div>

            <!--  Пагинация всегда прижата к низу и не скроллится -->
            <div class="shrink-0 pt-4 mt-2 border-t border-border" wire:key="pagination-support">
                {{ $this->chats->links('partials.pagination') }}
            </div>
        </div>

        <!-- Правая панель: Переписка -->      
        <div class="lg:col-span-2 flex flex-col bg-muted/10 rounded-lg px-4 pt-4 h-[calc(100vh-14rem)] overflow-hidden">
            @if($this->activeChatId)
                @php $chat = \App\Models\Chat::with('user2')->find($this->activeChatId); $user = $chat->user2; @endphp
                
                <!-- Шапка чата -->
                <div class="shrink-0 flex items-center justify-between border-b border-border pb-3 mb-4">
                    <div class="flex items-center gap-3">
                        <x-avatar src="{{ $user?->avatar_url }}" name="{{ $user?->name }}" size="sm" userId="{{ $user?->id }}" showStatus="true" />
                        <div>
                            <a href="{{ route('admin.users.show', $user->id) }}" class="hover:text-primary font-medium text-sm">{{ $user?->name }}</a>
                            <div class="text-xs text-muted-foreground">ID: {{ $user?->id }} • {{ $user?->email }}</div>
                        </div>
                    </div>
                    <x-ui.button wire:click="clearChat({{ $chat->id }})" wire:confirm="Удалить всю историю переписки с этим пользователем?" variant="ghost" size="icon-sm" title="Очистить историю">
                        <x-lucide-trash-2 class="w-4 h-4 text-destructive" />
                    </x-ui.button>
                </div>

                <!-- Лента сообщений (Авто-скролл через Alpine $watch) -->
                <div wire:poll.10s="loadMessages" x-data x-init="
                        setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50);
                        $watch('$wire.messages', () => {
                            $nextTick(() => { setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50); });
                        });
                     " 
                     class="flex-1 min-h-0 overflow-y-auto space-y-4 pr-2 flex flex-col little-scroll">
                    
                    @forelse ($messages as $msg)
                        @php $isMe = $msg['sender_id'] === auth()->id(); @endphp
                        <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }}" wire:key="msg-{{ $msg['id'] }}">
                            <div class="max-w-[80%]">
                                <div class="text-left whitespace-pre-line break-words {{ $isMe ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground' }} rounded-2xl px-4 py-2 text-sm">{{ trim($msg['body']) }}</div>
                                <div class="text-[0.65rem] text-muted-foreground mt-1 {{ $isMe ? 'text-right' : 'text-left' }}">
                                    {{ \Carbon\Carbon::parse($msg['created_at'])->format('d.m H:i') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-sm text-muted-foreground py-8">Начните диалог.</div>
                    @endforelse
                </div>

                <!-- Блок отправки -->
                <div class="shrink-0 mt-4 space-y-1">     
                    <div class="relative flex-1">
                        <x-ui.context-menu class="w-full">
                            <x-ui.context-menu-trigger class="block w-full">
                               <x-ui.textarea 
                                    x-data 
                                    @focus-message-input.window="$el.focus()" 
                                    wire:model="messageBody" 
                                    @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendMessage(); }" 
                                    placeholder="Введите сообщение..." 
                                    rows="3"
                                    class="bg-message w-full resize-none max-h-64 little-scroll pr-12 pb-3" 
                                />
                            </x-ui.context-menu-trigger>
                            
                            <x-ui.context-menu-content class="w-80" x-data="{ search: '', templates: {{ json_encode($this->flatTemplates) }} }">
                                <div class="px-4 py-2" @click.stop @contextmenu.prevent>
                                    <input type="text" x-model="search" placeholder="Поиск шаблона..." class="w-full px-2 py-1.5 text-sm border border-border rounded bg-card focus:outline-none focus:ring-1 focus:ring-primary" autocomplete="off">
                                </div>
                                <x-ui.context-menu-separator />
                                
                                <div class="max-h-60 overflow-y-auto little-scroll p-3">
                                    <template x-for="(template, index) in templates" :key="index">
                                        <div>
                                            <div x-show="search === '' && (index === 0 || templates[index-1].category !== template.category)" class="px-2 py-1.5 mt-1 text-[10px] font-bold uppercase text-muted-foreground/70">
                                                <span x-text="template.category"></span>
                                            </div>
                                            <x-ui.context-menu-item x-show="template.title.toLowerCase().includes(search.toLowerCase()) || template.category.toLowerCase().includes(search.toLowerCase())" @click="$wire.setTemplate(template.index)">
                                                <span x-text="template.title"></span>
                                            </x-ui.context-menu-item>
                                        </div>
                                    </template>
                                    <div x-show="!templates.some(t => t.title.toLowerCase().includes(search.toLowerCase()) || t.category.toLowerCase().includes(search.toLowerCase()))" class="px-2 py-4 text-center text-xs text-muted-foreground">
                                        Ничего не найдено
                                    </div>
                                </div>
                            </x-ui.context-menu-content>
                        </x-ui.context-menu>
                       
                        <x-ui.button wire:click="sendMessage" wire:loading.attr="disabled" size="icon" class="absolute bottom-3.5 right-3.5 shadow-md rounded-lg">
                            <x-lucide-send class="w-4 h-4" />
                        </x-ui.button>
                    </div>
                     <p class="text-xs text-muted-foreground flex items-center gap-1">
                        <x-lucide-mouse-pointer-click class="w-3 h-3" /> 
                        Правый клик по полю ввода для вставки шаблона.
                    </p>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-muted-foreground">
                    <x-lucide-inbox class="w-16 h-16 mb-4 opacity-20" />
                    <h3 class="text-lg font-medium">Выберите тикет</h3>
                </div>
            @endif
        </div>
    </div>
</div>