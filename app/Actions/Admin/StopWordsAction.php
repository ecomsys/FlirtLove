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
     * Массовое создание стоп-слов (HIGH-LOAD ОПТИМИЗАЦИЯ)
     */
    public function createBulk(string $wordsStr, StopWordCategory $category, StopWordAction $action, User $admin): int
    {
        // Разбиваем строку по запятым, переносам строк, точкам с запятой
        $words = preg_split('/[\r\n,;]+/', $wordsStr);
        
        $data = [];
        $now = now();
        
        foreach ($words as $wordStr) {
            $wordStr = trim($wordStr);
            if ($wordStr === '') continue;
            
            // Защита от слишком длинных строк (ограничение БД 255)
            $wordStr = substr($wordStr, 0, 255);
            
            $data[] = [
                'word' => $wordStr,
                'category' => $category->value,
                'action' => $action->value,
                'replacement' => '***', // Дефолт
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($data)) return 0;

        // ОДИН bulk-запрос. insertOrIgnore пропустит дубликаты (благодаря unique индексу на word)
        // Возвращает количество реально вставленных строк!
        $createdCount = StopWord::insertOrIgnore($data);

        if ($createdCount > 0) {
            $after = [
                'status' => 'created', 
                'count' => $createdCount,
                'context' => [
                    'category' => $category->value,
                    'action' => $action->value,
                    'examples' => array_slice(array_column($data, 'word'), 0, 5)
                ]
            ];

            AdminLog::record('stop_words.create', null, $admin, null, $after);
        }

        $this->clearCache();
        return $createdCount;
    }

    public function toggleActive(int $id, User $admin): void
    {
        $word = StopWord::find($id);
        if (!$word) return;

        $before = ['is_active' => $word->getOriginal('is_active')];
        
        $word->update(['is_active' => !$word->is_active]);
        
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
        
        AdminLog::record('stop_words.delete', $word, $admin, $before, $after);
        
        $word->delete();
        $this->clearCache();
    }

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

    private function clearCache(): void
    {
        Cache::forget('stop_words_active');
        Cache::forget('admin_stopword_counts');
    }
}