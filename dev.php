<?php
/*
* Что это?
* Скрипт dev.php — это универсальная команда для одного запуска всех сервисов проекта:
* Веб-сервер — php artisan serve (основной, логи выводятся в текущем терминале)
* Сборщик фронтенда — npm run dev (Vite, запускается в отдельном окне)
* Планировщик задач — php artisan schedule:work (отдельное окно)
* Очередь заданий — php artisan queue:work (отдельное окно)
* Тяжелая очередь — php artisan queue:work --queue=heavy (отдельное окно)
* Очередь трансляций — php artisan queue:work --queue=broadcasts (отдельное окно)
* После успешного старта автоматически открывается браузер с админкой.
*/

$checkHost = '127.0.0.1'; // Используем IP вместо localhost, чтобы избежать зависания на IPv6
$port = 8000;
$url = "http://localhost:$port/admin"; // Для браузера оставляем localhost

$isWindows = (PHP_OS_FAMILY === 'Windows');

// Проверка порта перед стартом
$fp = @fsockopen($checkHost, $port, $errno, $errstr, 0.5);
if ($fp) {
    fclose($fp);
    echo "⚠️ Порт $port уже занят. Возможно, сервер уже запущен.\n";
    exit(1);
}

/**
 * Открыть новое окно терминала и выполнить команду (асинхронно)
 */
function openTerminal($command, $title = '')
{
    if (PHP_OS_FAMILY === 'Windows') {
        $bashPaths = [
            'C:\\Program Files\\Git\\git-bash.exe',
            'C:\\Program Files (x86)\\Git\\git-bash.exe',
        ];
        $bashFound = false;
        foreach ($bashPaths as $path) {
            if (file_exists($path)) {
                $bashFound = true;
                $cmd = "start \"$title\" \"$path\" -c \"$command\" --hold";
                pclose(popen($cmd, "r"));
                break;
            }
        }
        if (!$bashFound) {
            $cmd = "start \"$title\" cmd /k \"$command\"";
            pclose(popen($cmd, "r"));
        }
    } elseif (PHP_OS === 'Darwin') {
        $cmd = "osascript -e 'tell app \"Terminal\" to do script \"$command\"'";
        exec($cmd);
    } else {
        $cmd = "gnome-terminal -- bash -c \"$command; exec bash\" 2>/dev/null || " .
               "xterm -e bash -c \"$command; exec bash\" 2>/dev/null || " .
               "konsole -e bash -c \"$command; exec bash\" 2>/dev/null";
        exec($cmd);
    }
}

echo "  Запуск фоновых сервисов...\n";

// Запускаем все сервисы в отдельных окнах
openTerminal('npm run dev', 'Vite');
openTerminal('php artisan schedule:work', 'Schedule');
openTerminal('php artisan queue:work --queue=default', 'Queue Default');
openTerminal('php artisan queue:work --queue=heavy', 'Queue Heavy');
openTerminal('php artisan queue:work --queue=broadcasts', 'Queue Broadcasts');

sleep(2);

echo "  Фоновые сервисы запущены в отдельных окнах.\n";
echo "  Ожидание готовности основного сервера...\n";

// Запускаем основной сервер в текущем терминале
$descriptorSpec = [0 => STDIN, 1 => STDOUT, 2 => STDERR];
$process = proc_open('php artisan serve', $descriptorSpec, $pipes);

if (!is_resource($process)) {
    echo "❌ Не удалось запустить сервер.\n";
    exit(1);
}

// Ждём готовности порта БЕСКОНЕЧНО, но проверяем, не упал ли процесс
$attempt = 0;
$ready = false;

while (true) {
    $attempt++;
    
    // 1. Проверяем, открылся ли порт
    $fp = @fsockopen($checkHost, $port, $errno, $errstr, 0.5);
    if ($fp) {
        fclose($fp);
        $ready = true;
        break;
    }
    
    // 2. Проверяем, не умер ли процесс сервера (например, из-за ошибки в коде)
    $status = proc_get_status($process);
    if (!$status['running']) {
        echo "❌ Процесс сервера аварийно завершился до того, как начал слушать порт.\n";
        echo "   Проверьте логи выше, возможно там есть ошибка PHP.\n";
        proc_close($process);
        exit(1);
    }

    // Выводим сообщение каждые 2 секунды (4 попытки), чтобы не спамить в консоль
    if ($attempt % 4 === 0) {
        echo "   Сервер еще запускается... Прошло " . ($attempt / 2) . " сек.\n";
    }
    
    usleep(500000); // 0.5 секунды
}

echo "  ✅ Сервер готов!\n";

// Открываем браузер с /admin
echo "  Открываем браузер...\n";
if ($isWindows) {
    // Используем pclose/popen и пустые кавычки "", чтобы start не воспринял URL как заголовок окна
    pclose(popen("start \"\" $url", "r"));
} elseif (PHP_OS === 'Darwin') {
    exec("open $url");
} else {
    exec("xdg-open $url");
}

echo "----------------------------------------\n";
echo "   Логи сервера выводятся здесь.\n";
echo "   Нажмите Ctrl+C для остановки сервера.\n";
echo "   Окна Vite, Schedule и очередей закройте вручную.\n";
echo "   Запущены очереди: default, heavy, broadcasts\n";
echo "----------------------------------------\n";

// Ожидаем завершения сервера (держим скрипт активным)
while (true) {
    $status = proc_get_status($process);
    if (!$status['running']) {
        break;
    }
    sleep(1);
}

proc_close($process);
echo "\n🛑 Сервер остановлен. Закройте остальные окна при необходимости.\n";