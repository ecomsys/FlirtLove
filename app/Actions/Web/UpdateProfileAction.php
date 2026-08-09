<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserProfile;
use Carbon\Carbon;

class UpdateProfileAction
{
    /**
     * Обновить данные анкеты пользователя.
     *
     * @param User $user Текущий юзер
     * @param array $data Валидированные данные из Request (bio, body_type, interests и т.д.)
     * @return UserProfile
     */
    public function execute(User $user, array $data): UserProfile
    {
        $profile = $user->profile;

        // 1. Если юзер обновил дату рождения -> Автоматически считаем Знак Зодиака!
        if (isset($data['birth_date']) && $data['birth_date']) {
            $data['zodiac_sign'] = $this->calculateZodiacSign(Carbon::parse($data['birth_date']));
        }

        // 2. Если юзер загрузил список интересов, языков или спорта
        // Eloquent сам превратит массивы [1, 2, 3] в JSON благодаря $casts в модели,
        // поэтому мы просто передаем их как есть.

        // 3. Обновляем профиль
        // Метод update() берет только те ключи из $data, которые есть в $fillable модели UserProfile.
        // Это значит, что если из контроллера случайно прилетит поле 'is_admin' или 'user_id',
        // оно просто проигнорируется! Защита от массового заполнения (Mass Assignment) работает на 100%.
        $profile->update($data);

        // 4. Проверяем, заполнил ли юзер обязательные поля для появления в ленте
        // (У тебя был has_completed_onboarding в User, мы обновим его статус)
        $this->checkOnboardingCompletion($user, $profile);

        return $profile;
    }

    /**
     * Автоматически вычислить знак зодиака по дате рождения.
     * (Классическая фича дейтинг-сайтов)
     */
    private function calculateZodiacSign(Carbon $date): string
    {
        $day = $date->day;
        $month = $date->month;

        return match ($month) {
            1 => ($day <= 19) ? 'Козерог' : 'Водолей',
            2 => ($day <= 18) ? 'Водолей' : 'Рыбы',
            3 => ($day <= 20) ? 'Рыбы' : 'Овен',
            4 => ($day <= 19) ? 'Овен' : 'Телец',
            5 => ($day <= 20) ? 'Телец' : 'Близнецы',
            6 => ($day <= 20) ? 'Близнецы' : 'Рак',
            7 => ($day <= 22) ? 'Рак' : 'Лев',
            8 => ($day <= 22) ? 'Лев' : 'Дева',
            9 => ($day <= 22) ? 'Дева' : 'Весы',
            10 => ($day <= 22) ? 'Весы' : 'Скорпион',
            11 => ($day <= 21) ? 'Скорпион' : 'Стрелец',
            12 => ($day <= 21) ? 'Стрелец' : 'Козерог',
            default => 'Не указано',
        };
    }

    /**
     * Проверка: заполнен ли профиль enough для показа в ленте?
     * Если юзер добавил имя, пол, возраст и главное фото -> Onboarding завершен.
     */
    private function checkOnboardingCompletion(User $user, UserProfile $profile): void
    {
        // Простая логика: если есть пол, возраст и хотя бы 1 фото, анкета готова к показу
        $isComplete = $profile->gender !== null 
            && $profile->birth_date !== null 
            && $user->photos()->count() > 0;

        // Обновляем статус юзера
        if ($user->has_completed_onboarding !== $isComplete) {
            $user->update(['has_completed_onboarding' => $isComplete]);
        }
    }
}