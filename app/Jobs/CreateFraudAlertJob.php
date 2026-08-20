<?php

namespace App\Jobs;

use App\Enums\FraudAlertSeverity;
use App\Enums\FraudAlertStatus;
use App\Models\FraudAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Services\ContentFilterService;

class CreateFraudAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $triggerType, // Например: 'links_in_chat', 'stop_word_match'
        public FraudAlertSeverity $severity,
        public array $meta = []
    ) {}

    public function handle(): void
    {
        // Защита от спама алертами: проверяем, нет ли уже ОТКРЫТОГО алерта на этого юзера
        $existingAlert = FraudAlert::where('user_id', $this->userId)
            ->where('trigger_type', $this->triggerType)
            ->where('status', FraudAlertStatus::Open)
            ->exists();

        if ($existingAlert) {
            return; // Уже есть открытый алерт, ничего не делаем
        }

        FraudAlert::create([
            'user_id' => $this->userId,
            'trigger_type' => $this->triggerType,
            'severity' => $this->severity->value,
            'meta' => $this->meta,
            'status' => FraudAlertStatus::Open->value,
        ]);
    }
}

//  Подготовить ядро (сервисы и джобы) заранее — это разделение ответственности. Когда дойдет до веба, в контроллерах и Livewire у нас будет всего 1-2 строки кода.

// Мы создадим ContentFilterService (сам фильтр) и CreateFraudAlertJob (очередь для создания алертов), чтобы не тормозить ответ сервера юзеру.

// Шаг 1: Создаем CreateFraudAlertJob
// Воркер, который создает алерт. Здесь есть важная защита: мы проверяем, нет ли уже открытого алерта у этого юзера по такому же триггеру. Если бот шлет 10 сообщений подряд со ссылкой, мы создадим только 1 алерт, чтобы не засрать базу.

// Создай файл app/Jobs/CreateFraudAlertJob.php:

// Шаг 2: Создаем ядро — ContentFilterService
// Этот сервис будет кэшировать стоп-слова и проверять текст.
// Важно: я добавил безопасную обработку регулярок. Если админ криво напишет регулярку в админке, preg_match не упадет с 500 ошибкой, а просто пропустит правило.

// Создай файл app/Services/ContentFilterService.php:

// Шаг 3: Регистрируем сервис в Провайдере
// Чтобы сервис можно было легко внедрять (Dependency Injection) в любые контроллеры или Livewire компоненты, зарегистрируй его.

// Открой app/Providers/AppServiceProvider.php и добавь в метод register():

// Как это будет использоваться в вебе (Пример)
// Когда ты будешь писать контроллер для чата или Livewire компонент, тебе не придется писать кучу логики. Всё уложится в 5 строк:

// php

// use App\Services\ContentFilterService;

// public function sendMessage(Request $request, ContentFilterService $filter)
// {
//     $text = $request->input('text');
//     $userId = auth()->id();

//     // Прогоняем через фильтр
//     $result = $filter->filter($text, $userId, 'links_in_chat');

//     if ($result['is_rejected']) {
//         return back()->withErrors(['text' => 'Сообщение содержит запрещенные элементы.']);
//     }

//     // Сохраняем в БД (текст уже замаскирован, если нужно)
//     Message::create([
//         'body' => $result['text'],
//         // ...
//     ]);

//     return response()->json(['success' => true]);
// }
// Почему это уровень Senior:
// Кэширование: База не напрягается вообще. Стоп-слова лежат в Redis/Cache.
// Асинхронность: Если слово найдено, юзер моментально получает ответ (его сообщение уходит), а алерт уходит в очередь RabbitMQ/Database. Юзер не ждет, пока база запишет FraudAlert.
// Безопасность: preg_match с @ не уронит сайт, если админ случайно введет '/[a-' в админке.
// Слабая связанность: Ты можешь вызывать $filter->filter() где угодно: в чате, в комментариях, при заполнении анкеты — логика одна и та же.
// Теперь бэкенд иммунной системы полностью готов! 