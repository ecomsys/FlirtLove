<?php

namespace App\Services;

use App\Enums\FraudAlertSeverity;
use App\Enums\StopWordAction;
use App\Jobs\CreateFraudAlertJob;
use App\Models\StopWord;
use Illuminate\Support\Facades\Cache;

class StopWordsFilterService
{
    private const CACHE_KEY = 'stop_words_active';

    /**
     * Получить активные слова из кэша (на 24 часа или до сброса)
     */
    public function getActiveWords(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), function () {
            return StopWord::where('is_active', true)->get()->toArray();
        });
    }

    /**
     * Главный метод фильтрации текста
     * 
     * @param string $text Исходный текст
     * @param int $userId ID юзера, который пишет текст (для алертов)
     * @param string $triggerType Тип триггера (например, 'links_in_chat')
     * @return array ['text' => string, 'is_rejected' => bool]
     */
    public function filter(string $text, int $userId, string $triggerType = 'stop_word_match'): array
    {
        $words = $this->getActiveWords();
        $isRejected = false;
        $alerts = [];

        foreach ($words as $stopWord) {
            $wordStr = $stopWord['word'];
            
            // Если начинается с '/', считаем что это регулярка (например, /тг\s*@[\w\d]+/i)
            $isRegex = str_starts_with($wordStr, '/');
            $pattern = $isRegex ? $wordStr : '/' . preg_quote($wordStr, '/') . '/iu';

            // Проверяем текст
            // @ подавляет ошибки, если регулярка кривая (PREG_BACKTRACK_LIMIT_ERROR и тд)
            $match = @preg_match($pattern, $text);

            if ($match === false || $match === 0) {
                continue; // Нет совпадения или ошибка регулярки — пропускаем
            }

            // Совпадение найдено! Смотрим, что делать
            $action = $stopWord['action'];

            switch ($action) {
                case StopWordAction::Mask->value:
                    $replacement = $stopWord['replacement'] ?? '***';
                    $replacedText = preg_replace($pattern, $replacement, $text);
                    // Если регулярка не сломалась и вернула строку — применяем. Иначе оставляем как есть.
                    if (!is_null($replacedText)) {
                        $text = $replacedText;
                    }
                    break;

                case StopWordAction::Reject->value:
                    $isRejected = true;
                    break 2; // Прерываем цикл, текст полностью невалиден

                case StopWordAction::Alert->value:
                    // Текст не меняем, но собираем улики
                    $alerts[] = [
                        'word' => $wordStr,
                        'category' => $stopWord['category'],
                        'matched_in' => $triggerType
                    ];
                    break;
            }
        }

        // Если были алерты, отправляем в очередь (в фон!)
        if (!empty($alerts)) {
            CreateFraudAlertJob::dispatch(
                $userId,
                $triggerType,
                FraudAlertSeverity::High, // По умолчанию кидаем как высокий приоритет
                ['matches' => $alerts, 'text_snippet' => \Str::limit($text, 200)]
            )->onQueue('antifraud');
        }

        return [
            'text' => $text,
            'is_rejected' => $isRejected
        ];
    }
}


//  Подготовить ядро (сервисы и джобы) заранее — это разделение ответственности. Когда дойдет до веба, в контроллерах и Livewire у нас будет всего 1-2 строки кода.

// Мы создадим StopWordsFilterService (сам фильтр) и CreateFraudAlertJob (очередь для создания алертов), чтобы не тормозить ответ сервера юзеру.

// Шаг 1: Создаем CreateFraudAlertJob
// Воркер, который создает алерт. Здесь есть важная защита: мы проверяем, нет ли уже открытого алерта у этого юзера по такому же триггеру. Если бот шлет 10 сообщений подряд со ссылкой, мы создадим только 1 алерт, чтобы не засрать базу.

// Создай файл app/Jobs/CreateFraudAlertJob.php:

// Шаг 2: Создаем ядро — StopWordsFilterService
// Этот сервис будет кэшировать стоп-слова и проверять текст.
// Важно: я добавил безопасную обработку регулярок. Если админ криво напишет регулярку в админке, preg_match не упадет с 500 ошибкой, а просто пропустит правило.

// Создай файл app/Services/StopWordsFilterService.php:

// Шаг 3: Регистрируем сервис в Провайдере
// Чтобы сервис можно было легко внедрять (Dependency Injection) в любые контроллеры или Livewire компоненты, зарегистрируй его.

// Открой app/Providers/AppServiceProvider.php и добавь в метод register():

// Как это будет использоваться в вебе (Пример)
// Когда ты будешь писать контроллер для чата или Livewire компонент, тебе не придется писать кучу логики. Всё уложится в 5 строк:

// php

// use App\Services\StopWordsFilterService;

// public function sendMessage(Request $request, StopWordsFilterService $filter)
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