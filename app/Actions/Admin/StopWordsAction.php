<?php

namespace App\Actions\Admin;

use App\Enums\StopWordAction;
use App\Enums\StopWordCategory;
use App\Models\AdminLog;
use App\Models\StopWord;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StopWordsAction
{
    /**
     * Массовое создание стоп-слов.
     */
    public function createBulk(string $wordsStr, StopWordCategory $category, StopWordAction $action, User $admin): int
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
            $after = [
                'status' => 'created', 
                'count' => $createdCount,
                'context' => [
                    'category' => $category->value,
                    'action' => $action->value,
                    'examples' => array_slice(array_values($createdWords), 0, 5) // Сохраняем только первые 5 слов как пример
                ]
            ];

            AdminLog::record('stop_words.create', null, $admin, null, $after);
        }

        $this->clearCache();
        return $createdCount;
    }

    /**
     * Переключение активности стоп-слова.
     */
    public function toggleActive(int $id, User $admin): void
    {
        $word = StopWord::find($id);
        if (!$word) return;

        $before = ['is_active' => $word->getOriginal('is_active')];
        
        $word->update(['is_active' => !$word->is_active]);
        $word->refresh();
        
        $after = [
            'is_active' => $word->is_active, 
            'context' => [
                'word_id' => $word->id,
                'word' => $word->word
            ]
        ];
        
        AdminLog::record('stop_words.toggle', $word, $admin, $before, $after);
        $this->clearCache();
    }

    /**
     * Удаление одного стоп-слова.
     */
    public function deleteWord(int $id, User $admin): void
    {
        $word = StopWord::find($id);
        if (!$word) return;

        $before = ['is_active' => $word->getOriginal('is_active')];
        
        $after = [
            'status' => 'destroyed', 
            'context' => [
                'word_id' => $word->id,
                'word' => $word->word
            ]
        ];
        
        // Пишем лог ДО удаления
        AdminLog::record('stop_words.delete', $word, $admin, $before, $after);
        
        $word->delete();
        $this->clearCache();
    }

    /**
     * Массовые действия со стоп-словами (activate, deactivate, delete).
     */
    public function applyBulk(array $ids, string $action, User $admin): void
    {
        if (empty($ids)) return;

        $count = count($ids);

        DB::transaction(function () use ($ids, $action) {
            match ($action) {
                'activate' => StopWord::whereIn('id', $ids)->update(['is_active' => true]),
                'deactivate' => StopWord::whereIn('id', $ids)->update(['is_active' => false]),
                'delete' => StopWord::whereIn('id', $ids)->delete(),
                default => null,
            };
        });

        $after = [
            'status' => $action, 
            'count' => $count,
            'context' => [
                'action' => $action,
                'affected_count' => $count
            ]
        ];

        AdminLog::record('stop_words.bulk_action', null, $admin, null, $after);

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