<?php

namespace App\Listeners;

use App\Models\UserPreference;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class MigrateGuestTheme
{
    public function __construct(private Request $request)
    {
    }

    public function handle(Login $event): void
    {
        $theme = $this->request->cookie('theme');

        // Браузер ничего осмысленного не принёс — не мигрируем
        if (! in_array($theme, ['light', 'dark'], true)) {
            return;
        }

        // Находим существующую запись ИЛИ получаем новый (несохранённый) экземпляр.
        // ВАЖНО: ищем ТОЛЬКО по user_id — без всяких theme-условий,
        // из-за которых updateOrCreate ломался на unique constraint.
        $preference = UserPreference::firstOrNew(['user_id' => $event->user->id]);

        // Тема уже задана (юзер выбирал её, возможно с другого устройства) —
        // БД приоритетнее cookie, ничего не трогаем.
        if (in_array($preference->theme, ['light', 'dark'], true)) {
            return;
        }

        // Записи нет (INSERT, user_id свободен — constraint не нарушится)
        // или запись есть с пустой темой (UPDATE). Оба случая безопасны.
        $preference->theme = $theme;
        $preference->save();
    }
}