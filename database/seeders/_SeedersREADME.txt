Вот подробный и лаконичный README.md, который описывает всю архитектуру. Можешь смело положить его в корень проекта или в docs/DB_SCHEMA.md.

🗺️ LovePlanet DB Schema Documentation
Полная документация базы данных сервиса знакомств. Архитектура оптимизирована под High-Load (PostGIS, денормализация, кэширование в таблицах).

👤 Пользователи и Авторизация
users
Базовая таблица пользователей. Содержит кэшированные флаги (is_premium, is_verified) для быстрой работы Middleware без JOIN-ов.

Ключи: Soft Deletes (для восстановления аккаунтов).
Индексы: По статусам, онбордингу и премиум-статусу.
user_profiles
Расширенные данные анкеты (ФИО, интересы, параметры внешности).

Специфика: Справочники хранятся как TINYINT для скорости. Геолокация реализована через PostGIS (geography type) с пространственным индексом для поиска "кто рядом" (ST_DWithin).
JSON: interests, self_portrait, body_decorations, languages, sports.
user_preferences
Настройки пользователя: фильтры поиска, приватность, лимиты, внутренняя валюта (кредиты).

JSON: search_filters, chat_filter_settings, email_settings (гранулярные настройки пушей/почты).
📸 Медиа и Контент
albums
Альбомы пользователей. Содержит дефолтный системный альбом и приватные (18+).

Денормализация: photos_count (кэш количества фото).
photos
Фотографии пользователей. Поддерживает множественные размеры (original, large, medium, thumb).

Модерация: status (pending/approved/rejected), moderated_by, reject_reason.
Хранение: Soft Deletes (файлы остаются на диске для СБ). Пути генерируются через хэш-папки (substr(md5, 0, 3)) для масштабирования ФС.
photo_comments
Комментарии под фотографиями (вложенные деревья через parent_id).

Денормализация: likes_count, reports_count, replies_count.
Модерация: Единый паттерн с photos.
❤️ Социальный граф (Взаимодействия)
swipes
Свайпы (лайки/дизлайки/суперлайки).

Логика: rewinded_at (для функции отмены свайпа без удаления записи).
user_matches
Взаимные симпатии (мэтчи).

Архитектура: Строгое правило user1_id < user2_id для защиты от дубликатов пар. Имеет статус unmatched для разрыва связи.
profile_views
История просмотров анкет (Кто смотрел мою анкету).

Логика: unique(['viewer_id', 'viewed_id']) — обновляет только время просмотра, не раздувая таблицу.
user_blocks
Глобальный Черный список. Блокировка скрывает юзера из ленты рекомендаций и чатов.

💬 Коммуникация (Чаты)
chats
Диалоги между пользователями (приватные и саппорт).

Денормализация: last_message_at (для сортировки списка диалогов без JOIN к messages).
chat_participants
Сводная таблица участников чата.

Денормализация: unread_count (счетчик непрочитанных сообщений).*E0A> E0A> * Настройки: is_hidden, is_muted, is_blocked (настройки конкретного диалога для юзера).
messages
Сообщения в чатах. Поддерживает типы: текст, фото, подарок, системное сообщение.

Модерация: Проверяются только фото в чате (type=image -> status=pending).
Хранение: Soft Deletes (для СБ). sender_id nullable для системных сообщений.
💳 Монетизация и Финансы
subscription_plans
Справочник тарифов VIP-подписок.

JSON: features (Feature Flags: invisible, likes_per_day и т.д.).
Интеграция: apple_product_id, google_product_id (для In-App Purchases).
user_subscriptions
История покупок подписок.

Логика: Разделение отмены автопродления (canceled_at) и фактического истечения (ends_at). Требование Apple App Store.
transactions
Финансовые транзакции (подписки, микротранзакции, возвраты).

Интеграция: provider_transaction_id (ID от ЮKassa/Stripe).
JSON: meta (сырые данные от банка, причины ошибок).
gifts
Справочник виртуальных подарков (каталог магазина).

user_gifts
История отправленных подарков.

Снапшот: snapshot_name, snapshot_image_url, snapshot_price (защита от изменения цен в каталоге задним числом).
🛡️ Безопасность и Модерация
reports
Жалобы пользователей.

Связи: Полиморфная (reportable_type, reportable_id) — позволяет жаловаться на User, Photo, Message.
fraud_alerts
Антифрод-алерты (сработки системы безопасности).

Логика: severity (low, medium, high). Алерты с high могут автобанить юзера.
stop_words
Стоп-слова и правила антифрода.

Логика: action (mask, reject, alert). alert — стратегия "Honeypot" (западня): сообщение пропускается, но кидается алерт в fraud_alerts.
verifications
Заявки на верификацию личности (синяя галочка).

Модерация: Очередь для админки (pending/approved/rejected). При одобрении ставит users.is_verified = true.
🕵️ Администрация и Система
admin_logs
Аудит-логи действий администрации (кого забанил, что изменил).

Диффы: before, after (JSON). Позволяют откатить действия в один клик.
broadcasts
Массовые рассылки (In-App, Email, Push).

Аудитория: target_audience (JSON для сегментации).
Денормализация: total_recipients, sent_count, failed_count (для прогресс-бара админки).
settings
Динамические настройки сайта (лимиты, SEO, контакты).

UI: type, label, description (для автогенерации форм в админке). Кэшируется в Redis (Setting::get()).
pages
Статичные страницы (Политика конфиденциальности, Оферта).

CMS: Управление контентом из админки без деплоя.
📖 Блоги / Дневники
rubrics
Справочник рубрик для дневников (Мысли, Поэзия, О любви).

diaries
Посты пользователей (дневники).

Статусы: draft, published.
Денормализация: views_count, comments_count.
diary_subscriptions
Подписки на дневники (Many-to-Many между юзерами).

Логика: subscriber_id (кто подписан), author_id (на кого подписан).
🏗️ Ключевые архитектурные паттерны
Denormalization: Счетчики (photos_count, unread_count, views_count) и кэши времени (last_message_at) вынесены в таблицы для избежания COUNT() и MAX() запросов на лету.
Honeypot Strategy: Стоп-слова с action=alert пропускают текст мошенников, но тихо логируют их в fraud_alerts.
Snapshot Pattern: user_gifts сохраняют данные каталога на момент отправки (snapshot_price), защищая от изменения истории транзакций.
Min/Max Match Rule: В user_matches ID юзеров всегда записываются по правилу user1_id < user2_id, что исключает дубликаты мэтчей на уровне БД.
PostGIS: Геолокация реализована нативно через geography(point) и ST_DWithin для миллисекундного поиска в радиусе.
Idempotent Transactions: spendCredits использует атомарный DB::decrement с where('credits', '>=', $amount), защищая от Race Conditions.
Apple/Google Compliance: Разделение отмены подписки и её истечения (canceled_at vs ends_at).