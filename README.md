

# FlirtLove — Admin Panel (High-Load Architecture)

Добро пожаловать в проект FlirtLove! Это панель администрирования для дейтинг-платформы, спроектированная с учетом высоких нагрузок (High-Load) и рассчитанная на миллионы пользователей.

Этот гайд поможет быстро развернуть локальное окружение и запустить все сервисы.

## Минимально необходимые расширения для VS-Code:
```bash
1.Laravel Blade Snippets - даст VS Code грамматику языка Blade 
2.Blade Formatter — чтобы Shift+Alt+F красиво форматировало Blade, а не ломало его. 
3.Tailwind CSS IntelliSense — автоподсказка классов. 
4.PHP Intelephense — лучший автокомплит для PHP. 
5.Prettier — для форматирования JS/JSON/CSS. 
6.ESLint
```

## Минималоьный список расширений раскомментированных рсширений в php.ini

```bash
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_pgsql
extension=pdo_sqlite
extension=pgsql
; Рекомендуется установить Imagick для экономии памяти при ресайзе фото
; extension=imagick 
```

## Лимиты загрузки файлов:

```bash
upload_max_filesize = 20M
memory_limit = 256M
```

## Требования

```bash
PHP ≥ 8.1 (с расширениями: pgsql, pdo_pgsql, zip, gd, mbstring, json, curl, fileinfo)
Composer (установщик зависимостей PHP)
Node.js ≥ 18 (для Vite и npm)
PostgreSQL ≥ 13 (или 14, 15)
Git (для клонирования)
Git Bash (рекомендуется для Windows, но не обязательно)
```

## Установка и запуск

```bash
# Клонирование репозитория
git clone https://github.com/ecomsys/FlirtLove.git
cd FlirtLove

# Установка зависимостей
composer install
npm install

# Сборка ассетов (для прода) или npm run dev (для локалки)
npm run build 

# Настройка окружения
cp .env.example .env
php artisan key:generate
```

## Настройка базы данных (.env)

Отредактируйте .env, указав данные для подключения к PostgreSQL:

```bash
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flirtlove_db
DB_USERNAME=ваш_пользователь
DB_PASSWORD=ваш_пароль

# ВАЖНО: Для локалки используем file, чтобы не спамить запросами в БД.
# На проде ОБЯЗАТЕЛЬНО используем redis!
CACHE_STORE=file
```

## Создайте базу данных (через терминал createdb flirtlove_db или pgAdmin) и запустите миграции с сидерами:

```bash
php artisan db:rebuild
```

## В корне проекта есть скрипт dev.php. Он запускает все необходимые сервисы одной командой:
```bash
php dev.php
```

Что он делает под капотом:
```bash
npm run dev — Запускает Vite для горячего обновления фронтенда.
php artisan serve — Запускает PHP сервер (http://localhost:8000).
php artisan schedule:work — Планировщик задач (крон-задачи в реальном времени).
php artisan queue:work — Воркер очереди default (быстрые задачи: письма, пуши).
php artisan queue:work --queue=heavy — Воркер очереди heavy (тяжелые задачи: обработка фото).
php artisan queue:work --queue=broadcasts — Воркер рассылок.
авто запуск браузера [http://localhost:8000/admin]

```
Если скрипт не срабатывает, запустите эти команды в разных вкладках терминала вручную.


## Логин и пароль АДМИНИСТРАТОРА

```bash
login: admin@admin.com
password: 12121212
```

ВАЖНО ! Инструкция по SSL для карт: Для корректного определения адреса по карте (GeoIP) локально установите SSL-сертификат. Инструкция в файле RAEDME-DOCS/MAP-SSL-GET-ADRESS.md.


# Архитектурные стандарты (ОБЯЗАТЕЛЬНО К ПРОЧТЕНИЮ)

Этот проект спроектирован для Million-Load. Перед написанием кода ознакомьтесь с правилами:

1.Защита PostgreSQL от дробных ID: В поиске по ID всегда используйте ctype_digit($search) вместо is_numeric($search). Иначе запрос ?q=1.5 уронит базу фатальной ошибкой.

2.Запрет N+1 в циклах: Если нужно обновить 100 записей, используйте Bulk-запросы: Model::whereIn('id', $ids)->update(...). Никаких foreach ($models as $model) { $model->update(); }.

3.Скаляры в Очередях (ShouldQueue): В конструкторы Job'ов и Уведомлений (Notifications) ЗАПРЕЩЕНО передавать Eloquent-модели. Передавайте только ID и строки. Это спасет Redis от переполнения и исключит ModelNotFoundException.

4.Убийцы refresh(): Метод $model->update() уже обновляет атрибуты в памяти. Использование $model->refresh() после update() создает лишний SELECT запрос к базе. Избегайте его.

5.Кэширование тяжелых Count-запросов: Все COUNT(*) с GROUP BY или SUM(CASE WHEN...) на страницах списков (Datatables) должны быть обернуты в Cache::remember(..., 60, ...) и сбрасываться в Action-классе при создании/удалении записи.

6.Eager Loading: При выводе списков с аватарками всегда используйте with(['photos' => fn($q) => $q->limit(1)]).
