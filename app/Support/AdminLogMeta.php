<?php

namespace App\Support;

class AdminLogMeta
{
    public static function get(string $action): array
    {
        $map = [
            // Баны и Статусы
            'user.ban' => ['title' => 'Блокировка', 'badge' => ['variant' => 'destructive', 'label' => 'Бан'], 'icon' => 'shield-off', 'iconColor' => 'text-destructive bg-destructive/10'],
            'user.mass_ban' => ['title' => 'Блокировка', 'badge' => ['variant' => 'destructive', 'label' => 'Бан'], 'icon' => 'shield-off', 'iconColor' => 'text-destructive bg-destructive/10'],
            'user.shadowban' => ['title' => 'Теневой бан', 'badge' => ['variant' => 'warning', 'label' => 'Теневой'], 'icon' => 'eye-off', 'iconColor' => 'text-purple-500 bg-purple-500/10'],
            'user.unban' => ['title' => 'Снятие блокировки', 'badge' => ['variant' => 'success', 'label' => 'Разбан'], 'icon' => 'shield-check', 'iconColor' => 'text-green-500 bg-green-500/10'],

            // Удаление/Восстановление
            'user.delete' => ['title' => 'Удаление аккаунта', 'badge' => ['variant' => 'secondary', 'label' => 'Удален'], 'icon' => 'trash-2', 'iconColor' => 'text-muted-foreground bg-muted'],
            'user.mass_delete' => ['title' => 'Удаление аккаунта', 'badge' => ['variant' => 'secondary', 'label' => 'Удален'], 'icon' => 'trash-2', 'iconColor' => 'text-muted-foreground bg-muted'],
            'user.restore' => ['title' => 'Восстановление аккаунта', 'badge' => ['variant' => 'success', 'label' => 'Восстановлен'], 'icon' => 'rotate-ccw', 'iconColor' => 'text-green-500 bg-green-500/10'],

            // Сессии
            'user.session_killed' => ['title' => 'Завершение сессии', 'badge' => ['variant' => 'warning', 'label' => 'Сессия'], 'icon' => 'smartphone', 'iconColor' => 'text-yellow-500 bg-yellow-500/10'],
            'user.all_sessions_killed' => ['title' => 'Завершение всех сессий', 'badge' => ['variant' => 'destructive', 'label' => 'Взлом?'], 'icon' => 'power', 'iconColor' => 'text-red-500 bg-red-500/10'],
            'user.role_change' => ['title' => 'Смена роли', 'badge' => ['variant' => 'info', 'label' => 'Роль'], 'icon' => 'shield-check', 'iconColor' => 'text-blue-500 bg-blue-500/10'],

            // Чаты
            'chat.lock' => ['title' => 'Блокировка чата', 'badge' => ['variant' => 'destructive', 'label' => 'Чат'], 'icon' => 'lock', 'iconColor' => 'text-destructive bg-destructive/10'],
            'chat.unlock' => ['title' => 'Разблокировка чата', 'badge' => ['variant' => 'success', 'label' => 'Чат'], 'icon' => 'unlock', 'iconColor' => 'text-green-500 bg-green-500/10'],

            // Фото
            'photo.approve' => ['title' => 'Фото одобрено', 'badge' => ['variant' => 'success', 'label' => 'Модерация'], 'icon' => 'check-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'photo.reject' => ['title' => 'Фото отклонено', 'badge' => ['variant' => 'destructive', 'label' => 'Модерация'], 'icon' => 'x-circle', 'iconColor' => 'text-destructive bg-destructive/10'],
            'photo.destroy' => ['title' => 'Фото удалено навсегда', 'badge' => ['variant' => 'destructive', 'label' => 'Удаление'], 'icon' => 'trash-2', 'iconColor' => 'text-destructive bg-destructive/10'],
            'photo.set_primary' => ['title' => 'Смена аватара', 'badge' => ['variant' => 'info', 'label' => 'Аватар'], 'icon' => 'image', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
            'photo.mass_approve' => ['title' => 'Массовое одобрение фото', 'badge' => ['variant' => 'success', 'label' => 'Массовое'], 'icon' => 'check', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'photo.mass_reject' => ['title' => 'Массовое отклонение фото', 'badge' => ['variant' => 'destructive', 'label' => 'Массовое'], 'icon' => 'x', 'iconColor' => 'text-destructive bg-destructive/10'],
            'photo.soft_delete' => ['title' => 'Фото в карантине', 'badge' => ['variant' => 'warning', 'label' => 'Карантин'], 'icon' => 'archive', 'iconColor' => 'text-yellow-500 bg-yellow-500/10'],
            'photo.restore' => ['title' => 'Фото восстановлено', 'badge' => ['variant' => 'info', 'label' => 'Восстановление'], 'icon' => 'rotate-ccw', 'iconColor' => 'text-blue-500 bg-blue-500/10'],

            // Рубрики блога
            'blog_category.create' => ['title' => 'Создана рубрика блога', 'badge' => ['variant' => 'success', 'label' => 'Блог'], 'icon' => 'folder-plus', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'blog_category.delete' => ['title' => 'Удалена рубрика блога', 'badge' => ['variant' => 'destructive', 'label' => 'Блог'], 'icon' => 'folder-minus', 'iconColor' => 'text-destructive bg-destructive/10'],

            // Шаблоны поддержки
            'support_template.create' => ['title' => 'Создан шаблон поддержки', 'badge' => ['variant' => 'success', 'label' => 'Шаблоны'], 'icon' => 'plus-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'support_template.update' => ['title' => 'Изменен шаблон поддержки', 'badge' => ['variant' => 'info', 'label' => 'Шаблоны'], 'icon' => 'edit', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
            'support_template.delete' => ['title' => 'Удален шаблон поддержки', 'badge' => ['variant' => 'destructive', 'label' => 'Шаблоны'], 'icon' => 'trash-2', 'iconColor' => 'text-destructive bg-destructive/10'],

            // Тарифы и подписки
            'plan.create' => ['title' => 'Создан тариф', 'badge' => ['variant' => 'success', 'label' => 'Тариф'], 'icon' => 'plus-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'plan.update' => ['title' => 'Изменен тариф', 'badge' => ['variant' => 'info', 'label' => 'Тариф'], 'icon' => 'edit', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
            'plan.toggle_active' => ['title' => 'Смена статуса тарифа', 'badge' => ['variant' => 'warning', 'label' => 'Тариф'], 'icon' => 'toggle-right', 'iconColor' => 'text-yellow-500 bg-yellow-500/10'],

            // Жалобы
            'report.resolve' => ['title' => 'Жалоба удовлетворена', 'badge' => ['variant' => 'destructive', 'label' => 'Жалоба'], 'icon' => 'shield-check', 'iconColor' => 'text-destructive bg-destructive/10'],
            'report.reject' => ['title' => 'Жалоба отклонена', 'badge' => ['variant' => 'secondary', 'label' => 'Жалоба'], 'icon' => 'shield-x', 'iconColor' => 'text-muted-foreground bg-muted'],

            // Комментарии
            'photo_comment.approve' => ['title' => 'Комментарий одобрен', 'badge' => ['variant' => 'success', 'label' => 'Модерация'], 'icon' => 'check-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'photo_comment.reject' => ['title' => 'Комментарий отклонен', 'badge' => ['variant' => 'destructive', 'label' => 'Модерация'], 'icon' => 'x-circle', 'iconColor' => 'text-destructive bg-destructive/10'],
            'photo_comment.spam' => ['title' => 'Комментарий помечен как спам', 'badge' => ['variant' => 'destructive', 'label' => 'Спам'], 'icon' => 'alert-octagon', 'iconColor' => 'text-red-500 bg-red-500/10'],
            'photo_comment.restore' => ['title' => 'Комментарий восстановлен', 'badge' => ['variant' => 'info', 'label' => 'Восстановление'], 'icon' => 'rotate-ccw', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
            'photo_comment.mass_approve' => ['title' => 'Массовое одобрение комментариев', 'badge' => ['variant' => 'success', 'label' => 'Массовое'], 'icon' => 'check', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'photo_comment.mass_reject' => ['title' => 'Массовое отклонение комментариев', 'badge' => ['variant' => 'destructive', 'label' => 'Массовое'], 'icon' => 'x', 'iconColor' => 'text-destructive bg-destructive/10'],

            // Антифрод
            'fraud_alert.resolve' => ['title' => 'Антифрод: Бан подтвержден', 'badge' => ['variant' => 'destructive', 'label' => 'Антифрод'], 'icon' => 'shield-alert', 'iconColor' => 'text-destructive bg-destructive/10'],
            'fraud_alert.warning' => ['title' => 'Антифрод: Вынесено предупреждение', 'badge' => ['variant' => 'warning', 'label' => 'Антифрод'], 'icon' => 'alert-triangle', 'iconColor' => 'text-yellow-500 bg-yellow-500/10'],
            'fraud_alert.false_positive' => ['title' => 'Антифрод: Ложное срабатывание', 'badge' => ['variant' => 'info', 'label' => 'Антифрод'], 'icon' => 'shield-check', 'iconColor' => 'text-blue-500 bg-blue-500/10'],

            // Рассылки (Уведомления)
            'broadcast.send_now' => ['title' => 'Запуск рассылки', 'badge' => ['variant' => 'info', 'label' => 'Рассылка'], 'icon' => 'send', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
            'broadcast.duplicate' => ['title' => 'Дублирование рассылки', 'badge' => ['variant' => 'secondary', 'label' => 'Рассылка'], 'icon' => 'copy', 'iconColor' => 'text-muted-foreground bg-muted'],
            'broadcast.delete' => ['title' => 'Удаление рассылки', 'badge' => ['variant' => 'destructive', 'label' => 'Рассылка'], 'icon' => 'trash-2', 'iconColor' => 'text-destructive bg-destructive/10'],
            'broadcast.create' => ['title' => 'Создана рассылка', 'badge' => ['variant' => 'success', 'label' => 'Рассылка'], 'icon' => 'plus-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'broadcast.update' => ['title' => 'Изменена рассылка', 'badge' => ['variant' => 'info', 'label' => 'Рассылка'], 'icon' => 'edit', 'iconColor' => 'text-blue-500 bg-blue-500/10'],

            // Поддержка
            'support.archive' => ['title' => 'Архивация тикета', 'badge' => ['variant' => 'secondary', 'label' => 'Поддержка'], 'icon' => 'archive', 'iconColor' => 'text-muted-foreground bg-muted'],
            'support.unarchive' => ['title' => 'Возврат тикета из архива', 'badge' => ['variant' => 'info', 'label' => 'Поддержка'], 'icon' => 'archive-restore', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
            'support.message_sent' => ['title' => 'Ответ службы поддержки', 'badge' => ['variant' => 'success', 'label' => 'Поддержка'], 'icon' => 'message-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],

            // Медиа
            'media.upload' => ['title' => 'Загрузка медиафайлов', 'badge' => ['variant' => 'success', 'label' => 'Медиа'], 'icon' => 'upload', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'media.delete' => ['title' => 'Удаление медиафайла', 'badge' => ['variant' => 'destructive', 'label' => 'Медиа'], 'icon' => 'image-off', 'iconColor' => 'text-destructive bg-destructive/10'],

            // Стоп-слова
            'stop_words.create' => ['title' => 'Создание стоп-слов', 'badge' => ['variant' => 'success', 'label' => 'Стоп-слова'], 'icon' => 'shield-ban', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'stop_words.toggle' => ['title' => 'Смена статуса стоп-слова', 'badge' => ['variant' => 'warning', 'label' => 'Стоп-слова'], 'icon' => 'shield-ban', 'iconColor' => 'text-yellow-500 bg-yellow-500/10'],
            'stop_words.delete' => ['title' => 'Удаление стоп-слова', 'badge' => ['variant' => 'destructive', 'label' => 'Стоп-слова'], 'icon' => 'shield-ban', 'iconColor' => 'text-destructive bg-destructive/10'],
            'stop_words.bulk_action' => ['title' => 'Массовое действие со стоп-словами', 'badge' => ['variant' => 'info', 'label' => 'Стоп-слова'], 'icon' => 'shield-ban', 'iconColor' => 'text-blue-500 bg-blue-500/10'],

            // Подарки (Каталог)
            'gift.create' => ['title' => 'Создан подарок', 'badge' => ['variant' => 'success', 'label' => 'Каталог'], 'icon' => 'plus-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'gift.update' => ['title' => 'Изменен подарок', 'badge' => ['variant' => 'info', 'label' => 'Каталог'], 'icon' => 'edit', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
            'gift.delete' => ['title' => 'Удален подарок', 'badge' => ['variant' => 'destructive', 'label' => 'Каталог'], 'icon' => 'trash-2', 'iconColor' => 'text-destructive bg-destructive/10'],
            'gift.deactivate' => ['title' => 'Скрыт подарок (был отправлен)', 'badge' => ['variant' => 'warning', 'label' => 'Каталог'], 'icon' => 'eye-off', 'iconColor' => 'text-yellow-500 bg-yellow-500/10'],
            'gift.toggle_status' => ['title' => 'Смена статуса подарка', 'badge' => ['variant' => 'secondary', 'label' => 'Каталог'], 'icon' => 'toggle-right', 'iconColor' => 'text-muted-foreground bg-muted'],

            // Подарки (История дарений)
            'user_gift.hide' => ['title' => 'Скрыт подарок из профиля', 'badge' => ['variant' => 'destructive', 'label' => 'Подарок'], 'icon' => 'eye-off', 'iconColor' => 'text-destructive bg-destructive/10'],
            'user_gift.restore' => ['title' => 'Возвращен подарок в профиль', 'badge' => ['variant' => 'success', 'label' => 'Подарок'], 'icon' => 'rotate-ccw', 'iconColor' => 'text-green-500 bg-green-500/10'],

            // Локация
            'user.location_update' => ['title' => 'Изменение локации', 'badge' => ['variant' => 'info', 'label' => 'Гео'], 'icon' => 'map-pin', 'iconColor' => 'text-blue-500 bg-blue-500/10'],

            // Профиль
            'profile.clear_field' => ['title' => 'Очистка текста', 'badge' => ['variant' => 'info', 'label' => 'Модерация'], 'icon' => 'eraser', 'iconColor' => 'text-blue-500 bg-blue-500/10'],

            // Рубрики дневников (Обновили ключи)
            'diary_rubric.create' => ['title' => 'Создана рубрика дневника', 'badge' => ['variant' => 'success', 'label' => 'Рубрика'], 'icon' => 'folder-plus', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'diary_rubric.update' => ['title' => 'Обновлена рубрика дневника', 'badge' => ['variant' => 'info', 'label' => 'Рубрика'], 'icon' => 'edit', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
            'diary_rubric.delete' => ['title' => 'Удалена рубрика дневника', 'badge' => ['variant' => 'destructive', 'label' => 'Рубрика'], 'icon' => 'trash-2', 'iconColor' => 'text-destructive bg-destructive/10'],
            'diary_rubric.toggle_status' => ['title' => 'Смена статуса рубрики дневника', 'badge' => ['variant' => 'warning', 'label' => 'Рубрика'], 'icon' => 'eye-off', 'iconColor' => 'text-yellow-500 bg-yellow-500/10'],

            // Дневники
            'diary.approve' => ['title' => 'Дневник одобрен', 'badge' => ['variant' => 'success', 'label' => 'Модерация'], 'icon' => 'check-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'diary.reject' => ['title' => 'Дневник отклонен', 'badge' => ['variant' => 'destructive', 'label' => 'Модерация'], 'icon' => 'x-circle', 'iconColor' => 'text-destructive bg-destructive/10'],
            'diary.unpublish' => ['title' => 'Дневник снят с публикации', 'badge' => ['variant' => 'warning', 'label' => 'Скрыт'], 'icon' => 'eye-off', 'iconColor' => 'text-yellow-500 bg-yellow-500/10'],
            'diary.delete' => ['title' => 'Дневник отправлен в карантин', 'badge' => ['variant' => 'secondary', 'label' => 'Карантин'], 'icon' => 'archive', 'iconColor' => 'text-muted-foreground bg-muted'],
            'diary.restore' => ['title' => 'Дневник восстановлен', 'badge' => ['variant' => 'info', 'label' => 'Восстановлен'], 'icon' => 'rotate-ccw', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
            'diary.force_delete' => ['title' => 'Дневник удален навсегда', 'badge' => ['variant' => 'destructive', 'label' => 'Удален'], 'icon' => 'trash-2', 'iconColor' => 'text-destructive bg-destructive/10'],

            // Комм. к дневникам
            'diary_comment.approve' => ['title' => 'Коммент. дневника одобрен', 'badge' => ['variant' => 'success', 'label' => 'Модерация'], 'icon' => 'check-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'diary_comment.reject' => ['title' => 'Коммент. дневника отклонен', 'badge' => ['variant' => 'destructive', 'label' => 'Модерация'], 'icon' => 'x-circle', 'iconColor' => 'text-destructive bg-destructive/10'],
            'diary_comment.spam' => ['title' => 'Коммент. дневника помечен как спам', 'badge' => ['variant' => 'destructive', 'label' => 'Спам'], 'icon' => 'alert-octagon', 'iconColor' => 'text-red-500 bg-red-500/10'],
            'diary_comment.restore' => ['title' => 'Коммент. дневника восстановлен', 'badge' => ['variant' => 'info', 'label' => 'Восстановление'], 'icon' => 'rotate-ccw', 'iconColor' => 'text-blue-500 bg-blue-500/10'],

            // Знакомства (Свайпы и Мэтчи)
            'swipe.destroy' => ['title' => 'Удаление свайпа', 'badge' => ['variant' => 'secondary', 'label' => 'Свайп'], 'icon' => 'trash-2', 'iconColor' => 'text-muted-foreground bg-muted'],
            'match.unmatch' => ['title' => 'Разрыв мэтча', 'badge' => ['variant' => 'destructive', 'label' => 'Мэтч'], 'icon' => 'heart-crack', 'iconColor' => 'text-destructive bg-destructive/10'],
            'match.restore' => ['title' => 'Восстановление мэтча', 'badge' => ['variant' => 'success', 'label' => 'Мэтч'], 'icon' => 'heart', 'iconColor' => 'text-green-500 bg-green-500/10'],

            // Транзакции
            'transaction.sync_success' => ['title' => 'Синхронизация платежа (Успех)', 'badge' => ['variant' => 'success', 'label' => 'Финансы'], 'icon' => 'check-circle', 'iconColor' => 'text-green-500 bg-green-500/10'],
            'transaction.sync_failed' => ['title' => 'Синхронизация платежа (Ошибка)', 'badge' => ['variant' => 'destructive', 'label' => 'Финансы'], 'icon' => 'x-circle', 'iconColor' => 'text-destructive bg-destructive/10'],
            'transaction.refund' => ['title' => 'Возврат средств (Refund)', 'badge' => ['variant' => 'warning', 'label' => 'Возврат'], 'icon' => 'rotate-ccw', 'iconColor' => 'text-yellow-500 bg-yellow-500/10'],

            // Блокировки
            'user_block.delete' => ['title' => 'Снятие блокировки', 'badge' => ['variant' => 'info', 'label' => 'Блокировка'], 'icon' => 'unlock', 'iconColor' => 'text-blue-500 bg-blue-500/10'],
        ];

        // Возвращаем данные, либо дефолтные значения, если экшен не найден в массиве
        return $map[$action] ?? [
            'title' => 'Действие',
            'badge' => ['variant' => 'secondary', 'label' => 'Лог'],
            'icon' => 'activity',
            'iconColor' => 'text-muted-foreground bg-muted'
        ];
    }
}
