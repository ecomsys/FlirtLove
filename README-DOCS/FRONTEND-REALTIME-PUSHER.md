# ФРОНТЕНД (Real-time Push)

Бэкенд готов на 100%. Теперь давай сделаем так, чтобы при одобрении/отклонении у пользователя в браузере красиво вылетало уведомление справа сверху, без перезагрузки страницы.

Выполняй по шагам:

# Шаг 1: Устанавливаем JS-зависимости
В терминале (в папке проекта) выполни:

```bash
npm install laravel-echo pusher-js
```

# Шаг 2: Настраиваем .env
Открой .env и измени/добавь эти строки. (Если у тебя пока нет реальных ключей Pusher, зарегистрируйся бесплатно на pusher.com, создай новый "Channels app" и скопируй ключи оттуда. Это займет 2 минуты).

```bash
BROADCAST_CONNECTION=pusher

PUSHER_APP_ID=твой_app_id
PUSHER_APP_KEY=твой_app_key
PUSHER_APP_SECRET=твой_app_secret
PUSHER_HOST=api-mt1.pusher.com
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

# Обязательно добавь VITE_ префикс для фронтенда!
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

# Шаг 3: Включаем Echo в resources/js/bootstrap.js
Открой этот файл, раскомментируй или добавь этот код в самый низ:

```bash
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    forceTLS: true
});
```
Убедись, что в resources/js/app.js есть строка import './bootstrap';

# Шаг 4: Создаем Alpine.js компонент уведомлений
Создай новый файл: resources/views/components/realtime-notifications.blade.php

```html
@auth
<div
    x-data="realtimeNotifications()"
    x-init="init()"
    class="fixed top-4 right-4 z-50 flex flex-col gap-3 pointer-events-none"
>
    <template x-for="notification in notifications" :key="notification.id">
        <div
            x-show="notification.visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-8"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-8"
            class="pointer-events-auto bg-white dark:bg-gray-800 border-l-4 shadow-lg rounded-md p-4 max-w-sm"
            :class="{
                'border-green-500': notification.status === 'approved' || notification.status === 'restored',
                'border-red-500': notification.status === 'rejected' || notification.status === 'deleted',
                'border-yellow-500': notification.status === 'spam',
            }"
        >
            <div class="flex items-start gap-3">
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="notification.title"></h4>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1" x-text="notification.message"></p>
                    
                    <a 
                        :href="`/photos/${notification.photo_id}#comment-${notification.comment_id}`" 
                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-2 inline-block flex items-center gap-1"
                    >
                        Перейти к комментарию
                        <x-lucide-arrow-right class="w-3 h-3" />
                    </a>
                </div>
                <button @click="remove(notification.id)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>
        </div>
    </template>
</div>

<script>
function realtimeNotifications() {
    return {
        notifications: [],
        
        init() {
            // Получаем ID текущего пользователя из Blade
            const userId = {{ auth()->id() }};
            
            // Подписываемся на приватный канал пользователя
            window.Echo.private(`users.${userId}`)
                .notification((notification) => {
                    // Реагируем только на наш тип уведомлений
                    if (notification.type === 'comment_moderated') {
                        this.add(notification);
                    }
                });
        },

        add(data) {
            const id = Date.now() + Math.random();
            this.notifications.push({
                id: id,
                ...data,
                visible: true
            });

            // Авто-скрытие через 6 секунд
            setTimeout(() => {
                this.remove(id);
            }, 6000);
        },

        remove(id) {
            const index = this.notifications.findIndex(n => n.id === id);
            if (index !== -1) {
                this.notifications[index].visible = false;
                setTimeout(() => {
                    this.notifications = this.notifications.filter(n => n.id !== id);
                }, 200);
            }
        }
    }
}
</script>
@endauth
```

# Шаг 5: Подключаем компонент в Layout
Открой свой главный layout (например, resources/views/layouts/app.blade.php или admin.blade.php) и добавь эту строку перед закрывающим тегом </body>:

```bash
<x-realtime-notifications />
```

# Шаг 6: Запускаем сборку и тестируем!

В одном терминале: npm run dev
В другом терминале: php artisan queue:work
Открой сайт в двух разных браузерах (или в режиме инкогнито):
В первом войди как обычный пользователь (автор комментария).
Во втором войди как админ/модератор.
В админке нажми "Одобрить" или "Отклонить" комментарий этого пользователя.
Смотри в браузер пользователя — красивая плашка должна вылететь справа сверху мгновенно! 🎯