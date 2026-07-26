<?php
/*
* Что это?
* Скрипт dev.php — это универсальная команда для одного запуска всех сервисов проекта:
* Веб-сервер — php artisan serve (основной, логи выводятся в текущем терминале)
* Сборщик фронтенда — npm run dev (Vite, запускается в отдельном окне)
* Планировщик задач — php artisan schedule:work (отдельное окно)
* Очередь заданий — php artisan queue:work (отдельное окно)
* После успешного старта автоматически открывается браузер с админкой: http://localhost:8000/admin.
* Скрипт ожидает, пока сервер не начнёт слушать порт 8000, и только тогда открывает браузер — гарантируется, 
* что страница загрузится без ошибок подключения.
*/

$host = 'localhost';
$port = 8000;
$url = "http://$host:$port/admin"; // ← здесь /admin

$isWindows = (PHP_OS_FAMILY === 'Windows');

// Проверка порта
$fp = @fsockopen($host, $port, $errno, $errstr, 0.5);
if ($fp) {
    fclose($fp);
    echo "⚠️ Порт $port уже занят. Возможно, сервер уже запущен.\n";
    exit(1);
}

/**
 * Открыть новое окно терминала и выполнить команду (асинхронно)
 * На Windows: сначала ищем Git Bash, если нет — используем cmd
 * На Linux/macOS: используем bash
 */
function openTerminal($command, $title = '')
{
    if (PHP_OS_FAMILY === 'Windows') {
        // Пути к Git Bash
        $bashPaths = [
            'C:\\Program Files\\Git\\git-bash.exe',
            'C:\\Program Files (x86)\\Git\\git-bash.exe',
        ];
        $bashFound = false;
        foreach ($bashPaths as $path) {
            if (file_exists($path)) {
                $bashFound = true;
                // Запускаем git-bash.exe с --hold, чтобы окно не закрылось
                // -c выполняет команду, а --hold оставляет окно открытым
                $cmd = "start \"$title\" \"$path\" -c \"$command\" --hold";
                pclose(popen($cmd, "r"));
                break;
            }
        }
        if (!$bashFound) {
            // Если Bash не найден — используем cmd
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

openTerminal('npm run dev', 'Vite');
openTerminal('php artisan schedule:work', 'Schedule');
openTerminal('php artisan queue:work', 'Queue');

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

// Ждём готовности порта (до 15 секунд)
$maxAttempts = 30;
$attempt = 0;
$ready = false;
while ($attempt < $maxAttempts) {
    $attempt++;
    $fp = @fsockopen($host, $port, $errno, $errstr, 0.5);
    if ($fp) {
        fclose($fp);
        $ready = true;
        break;
    }
    echo "   Попытка $attempt из $maxAttempts...\n";
    usleep(500000);
}

if (!$ready) {
    echo "❌ Сервер не запустился за отведённое время.\n";
    proc_terminate($process);
    exit(1);
}

echo "  Сервер готов!\n";

// Открываем браузер с /admin
echo "  Открываем браузер...\n";
if ($isWindows) {
    exec("start $url");
} elseif (PHP_OS === 'Darwin') {
    exec("open $url");
} else {
    exec("xdg-open $url");
}

echo "----------------------------------------\n";
echo "   Логи сервера выводятся здесь.\n";
echo "   Нажмите Ctrl+C для остановки сервера.\n";
echo "   Окна Vite, Schedule и Queue закройте вручную.\n";
echo "----------------------------------------\n";

// Ожидаем завершения сервера
$status = proc_get_status($process);
while ($status['running']) {
    sleep(1);
    $status = proc_get_status($process);
}
proc_close($process);
echo "\n🛑 Сервер остановлен. Закройте остальные окна при необходимости.\n";