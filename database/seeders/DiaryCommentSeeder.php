<?php

namespace Database\Seeders;

use App\Models\Diary;
use App\Models\DiaryComment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiaryCommentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💬 Генерация комментариев к дневникам...');

        // Очищаем старые комменты (если перезапускаем сидер)
        DB::statement('TRUNCATE TABLE diary_comments RESTART IDENTITY CASCADE');

        $users = User::excludeStaff()->pluck('id');
        
        if ($users->isEmpty()) {
            $this->command->error('Нет обычных юзеров для генерации комментариев!');
            return;
        }

        // Берем все дневники
        $diaries = Diary::all();

        if ($diaries->isEmpty()) {
            $this->command->error('Нет дневников! Сначала запустите DiarySeeder.');
            return;
        }

        // Шаблоны реальных комментариев для дейтинга
        $rootTexts = [
            "Очень интересная запись! Спасибо, что поделился(ась).",
            "Полностью согласен с тобой! Сам пришел к такому же выводу.",
            "А можно подробнее про второй пункт? Звучит интригующе.",
            "Какие красивые мысли. Надеюсь, найти такого же искреннего человека, как ты.",
            "Скучновато как-то... Но всем свое.",
            "Привет! Красиво пишешь. Может, познакомимся поближе?",
            "Это что, реклама? Удалил бы.",
            "Прочитал на одном дыхании. Жду новых постов!",
        ];

        $replyTexts = [
            "Спасибо за теплые слова!",
            "Да, конечно, напиши в личку, расскажу.",
            "Каждый имеет право на свое мнение.",
            "Давай попробуем пообщаться, ты мне тоже интересен(на).",
            "Никакой это не спам, просто делюсь опытом.",
            "Рад, что тебе понравилось!",
        ];

        $statuses = ['approved', 'approved', 'approved', 'pending', 'pending', 'rejected', 'spam'];

        foreach ($diaries as $diary) {
            $commentCount = rand(0, 5);

            for ($i = 0; $i < $commentCount; $i++) {
                $status = $statuses[array_rand($statuses)];
                $isModerated = in_array($status, ['approved', 'rejected', 'spam']);

                // Создаем корневой комментарий
                $rootComment = DiaryComment::create([
                    'diary_id' => $diary->id,
                    'user_id' => $users->random(),
                    'content' => $rootTexts[array_rand($rootTexts)],
                    'parent_id' => null,
                    'status' => $status,
                    'moderated_by' => $isModerated ? 1 : null, // ID админа
                    'moderated_at' => $isModerated ? now()->subDays(rand(1, 10)) : null,
                    'reject_reason' => $status === 'rejected' ? 'other' : ($status === 'spam' ? 'spam' : null),
                ]);

                // Шанс 40% что на этот коммент будет ответ (reply)
                if (rand(0, 100) < 40 && $status === 'approved') {
                    $replyStatus = $statuses[array_rand($statuses)];
                    $isReplyModerated = in_array($replyStatus, ['approved', 'rejected', 'spam']);

                    DiaryComment::create([
                        'diary_id' => $diary->id,
                        'user_id' => $diary->user_id, // Обычно отвечает автор поста
                        'content' => $replyTexts[array_rand($replyTexts)],
                        'parent_id' => $rootComment->id,
                        'status' => $replyStatus,
                        'moderated_by' => $isReplyModerated ? 1 : null,
                        'moderated_at' => $isReplyModerated ? now()->subDays(rand(0, 5)) : null,
                        'reject_reason' => $replyStatus === 'rejected' ? 'other' : ($replyStatus === 'spam' ? 'spam' : null),
                    ]);
                }
            }

            // Обновляем счетчик комментариев у поста (денормализация)
            $diary->update([
                'comments_count' => $diary->comments()->count()
            ]);
        }

        $this->command->info('✅ Комментарии к дневникам успешно сгенерированы!');
    }
}