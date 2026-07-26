# FlirtLove — запуск проекта для разработчиков

Добро пожаловать в проект FlirtLove!
Этот гайд поможет быстро развернуть локальное окружение и запустить все сервисы одной командой.

## Требования

```bash
PHP ≥ 8.1 (с расширениями: pgsql, pdo_pgsql, zip, gd, mbstring, json, curl, fileinfo)
Composer (установщик зависимостей PHP)
Node.js ≥ 18 (для Vite и npm)
PostgreSQL ≥ 13 (или 14, 15)
Git (для клонирования)
Git Bash (рекомендуется для Windows, но не обязательно)
```

## Клонирование репозитория

```bash
git clone https://github.com/ecomsys/FlirtLove.git
cd Flirtlove
```

## Установка зависимостей

```bash
composer install
npm install
npm run build 
cp .env.example .env
```

## Отредактируйте .env — укажите данные для подключения к PostgreSQL:

```bash
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flirtlove_db
DB_USERNAME=ваш_пользователь
DB_PASSWORD=ваш_пароль
```

## Создайте базу данных, если её ещё нет. Можно через терминал:
```bash
createdb flirtlove_db
```
Или через pgAdmin / Adminer.


##  Генерация ключа, миграции, запуск сидеров и запуск сервера(с сервисами)
```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
composer run start
```


## composer run start - самодельная команнда (запускает dev.php в корне проекта)

Что она делает ? Ровно :
```bash
npm run dev
php artisan serve
php artisan shedule:work    #  Планировщик задач - worker (отправка уведомлений и др.)
php artisan queue:work      #  Очередь заданий - worker (модерация фоток и др.)
запуск браузера [http://localhost:8000/admin]

```
можешь вместо нее использовать эти команды сам !

Для корректного определния адресса по карте установи ssl сертифика, инструкция в файле RAEDME-DOCS/MAP-SSL-GET-ADRESS.md

Удачи !



