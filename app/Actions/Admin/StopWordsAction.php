<?php

namespace App\Actions\Admin;

use App\Enums\StopWordAction;
use App\Enums\StopWordCategory;
use App\Models\AdminLog;
use App\Models\StopWord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StopWordsAction
{
    public function createBulk(string $wordsStr, StopWordCategory $category, StopWordAction $action): int
    {
        $words = preg_split('/[\r\n,;]+/', $wordsStr);
        $createdWords = [];
        $createdCount = 0;

        DB::transaction(function () use ($words, $category, $action, &$createdWords, &$createdCount) {
            foreach ($words as $wordStr) {
                $wordStr = trim($wordStr);
                if ($wordStr === '') continue;

                $word = StopWord::firstOrCreate(
                    ['word' => $wordStr],
                    [
                        'category' => $category, 
                        'action' => $action,     
                    ]
                );

                if ($word->wasRecentlyCreated) {
                    $createdWords[$word->id] = $wordStr;
                    $createdCount++;
                }
            }
        });

        if ($createdCount > 0) {
            AdminLog::record(
                'stop_words.bulk_create',
                null,
                Auth::user(),
                [],
                [
                    'description' => 'Массовое добавление стоп-слов',
                    'created_count' => $createdCount,
                    'category' => $category->value,
                    'action' => $action->value,
                    'words' => $createdWords 
                ]
            );
        }

        $this->clearCache();
        return $createdCount;
    }

    /**
     * Переключение активности стоп-слова (для одной строки).
     */
    public function toggleActive(int $id): void
    {
        $word = StopWord::find($id);
        if (!$word) return;

        // Пишем конкретное слово и его ID
        $before = ['id' => $word->id, 'word' => $word->word, 'is_active' => $word->is_active];
        
        $word->update(['is_active' => !$word->is_active]);
        
        $after = ['id' => $word->id, 'word' => $word->word, 'is_active' => $word->is_active];
        
        AdminLog::record('stop_words.toggle', $word, Auth::user(), $before, $after);
        $this->clearCache();
    }

    /**
     * Удаление одного стоп-слова.
     */
    public function deleteWord(int $id): void
    {
        $word = StopWord::find($id);
        if (!$word) return;

        // Сохраняем данные слова до удаления, чтобы в логе осталось ЧЕГО ИМЕННО лишилась база
        $logData = ['id' => $word->id, 'word' => $word->word, 'category' => $word->category, 'action' => $word->action, 'is_active' => $word->is_active];
        
        AdminLog::record('stop_words.delete', $word, Auth::user(), $logData, ['deleted' => true]);
        
        $word->delete();
        $this->clearCache();
    }

    /**
     * Массовые действия со стоп-словами (activate, deactivate, delete).
     */
    public function applyBulk(array $ids, string $action): void
    {
        if (empty($ids)) return;

        // 1. Выгружаем модели ДО изменения
        $words = StopWord::whereIn('id', $ids)->get();
        
        // Формируем массив для лога: [id => 'слово', ...] + статус
        $beforeData = $words->mapWithKeys(fn($w) => [
            $w->id => ['word' => $w->word, 'is_active' => $w->is_active]
        ])->toArray();

        // Человеко-читаемые названия
        $actionLabels = [
            'activate' => 'Массовая активация стоп-слов',
            'deactivate' => 'Массовая деактивация стоп-слов',
            'delete' => 'Массовое удаление стоп-слов'
        ];

        $afterData = [];

        // 2. Выполняем действие и формируем after-данные
        DB::transaction(function () use ($ids, $action, $words, &$afterData) {
            match ($action) {
                'activate' => StopWord::whereIn('id', $ids)->update(['is_active' => true]),
                'deactivate' => StopWord::whereIn('id', $ids)->update(['is_active' => false]),
                'delete' => StopWord::whereIn('id', $ids)->delete(),
                default => null,
            };

            // Если это не удаление, записываем, как стало
            if ($action !== 'delete') {
                $afterData = $words->fresh()->mapWithKeys(fn($w) => [
                    $w->id => ['word' => $w->word, 'is_active' => $w->is_active]
                ])->toArray();
            } else {
                // Если удаление, явно пишем, что они удалены
                foreach ($words as $w) {
                    $afterData[$w->id] = ['word' => $w->word, 'deleted' => true];
                }
            }
        });

        // 3. Пишем полный дифф в лог
        AdminLog::record(
            'stop_words.bulk_action', 
            null, 
            Auth::user(), 
            ['description' => $actionLabels[$action] ?? $action, 'affected' => $beforeData], // ЧТО БЫЛО
            ['action' => $action, 'result' => $afterData] // ЧТО СТАЛО
        );

        $this->clearCache();
    }

    /**
     * Сброс кэша (вызывается при любом изменении в БД).
     */
    private function clearCache(): void
    {
        Cache::forget('stop_words_active');
    }
}