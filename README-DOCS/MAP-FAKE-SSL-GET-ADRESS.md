При запросах к Nominatim (и любым HTTPS-адресам) через cURL PHP на Windows выдаёт ошибку:

text
SSL certificate: unable to get local issuer certificate
Решение — установить актуальный файл сертификатов (cacert.pem) и указать путь к нему в php.ini.

📥 1. Скачать сертификат
Скачай файл:
👉 https://curl.se/ca/cacert.pem

Сохрани как:
C:\cacert.pem

⚙️ 2. Настроить PHP
Найди файл php.ini. Узнать его путь можно командой в Tinker:

php
echo php_ini_loaded_file();
Открой php.ini любым редактором и добавь в самый конец (или найди секцию [curl]):

ini
curl.cainfo="C:\cacert.pem"
openssl.cainfo="C:\cacert.pem"
✅ 3. Проверить настройку
В Tinker выполни:

php
echo ini_get('curl.cainfo');
Если вернулось C:\cacert.pem — всё ок.

🔁 4. Перезапустить веб-сервер
Laragon/OpenServer — перезапусти.

php artisan serve — останови (Ctrl+C) и запусти снова.

🧪 5. Проверить работу адреса
Перейди на страницу пользователя → вкладка «Карта».
Если адрес появился — всё готово.

🚫 Временный обход (если нет времени)
В коде метода getAddressFromCoords() можно:

Заменить https на http в URL.

Или добавить curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

Но это не для продакшена!

🗂️ Где хранить файл
Файл можно положить в любое место (например, в папку проекта). Главное — указать правильный путь в php.ini.