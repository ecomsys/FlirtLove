<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;

class CaptchaService
{
    /**
     * Генерирует картинку капчи и сохраняет код в сессию.
     */
    public function generate(string $sessionKey = 'captcha'): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; 
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        Session::put($sessionKey, $code);

        // ФИКС 1: Увеличили холст, чтобы буквам было просторно
        $width = 350;
        $height = 70;
        $image = imagecreatetruecolor($width, $height);
        
        // Цвет фона (R=240, G=240, B=240)
        $bg_r = 240; $bg_g = 240; $bg_b = 240;
        $bg_color = imagecolorallocate($image, $bg_r, $bg_g, $bg_b);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);
        
        // Фоновые линии (шум)
        for ($i = 0; $i < 6; $i++) {
            $line_color = imagecolorallocate($image, random_int(180, 220), random_int(180, 220), random_int(180, 220));
            imageline($image, 0, random_int(0, $height), $width, random_int(0, $height), $line_color);
        }

        $x = 15;
        for ($i = 0; $i < strlen($code); $i++) {
            // 1. РАЗНОЦВЕТНЫЕ буквы
            $r = random_int(0, 180);
            $g = random_int(0, 180);
            $b = random_int(0, 180);
            $text_color = imagecolorallocate($image, $r, $g, $b);
            
            // 2. Мини-холст заливаем цветом фона
            $char_img = imagecreatetruecolor(30, 40);
            $char_bg = imagecolorallocate($char_img, $bg_r, $bg_g, $bg_b);
            imagefill($char_img, 0, 0, $char_bg);
            
            // Пишем букву
            imagestring($char_img, 5, 8, 12, $code[$i], $text_color);
            
            // 3. РАЗНЫЙ РАЗМЕР (Масштабируем от 1x до 2x)
            $scale_factor = mt_rand(130, 170) / 100;
            $new_width = (int)(30 * $scale_factor);
            $new_height = (int)(40 * $scale_factor);
            $scaled = imagescale($char_img, $new_width, $new_height);
            
            // 4. Поворот
            $angle = random_int(-25, 25);
            $rotated = imagerotate($scaled, $angle, $char_bg);
            
            // 5. ПЛЯШУТ по вертикали (оставили красивый разбег)
            $y = random_int(-10,10);
            
            // Вклеиваем букву
            $rotated_width = imagesx($rotated);
            imagecopy($image, $rotated, $x, $y, 0, 0, $rotated_width, imagesy($rotated));
            
            // ФИКС 2: Широкий шаг! Берем ширину повернутой буквы и добавляем отступ (от 20 до 35 пикселей)
            // Это гарантирует, что буквы будут на расстоянии и не будут накладываться!
            $x += $rotated_width + random_int(10, 14);
            
            imagedestroy($char_img);
            imagedestroy($scaled);
            imagedestroy($rotated);
        }

        // Разноцветные точки (шум)
        for ($i = 0; $i < 300; $i++) {
            $noise_color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $noise_color);
        }
        
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);
        
        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Проверяет введенный код против кода в сессии.
     * ОДНОРАЗОВО: удаляет код из сессии сразу после проверки!
     */
    public function validate(string $sessionKey, string $input): bool
    {
        $storedCode = Session::get($sessionKey);
        Session::forget($sessionKey); // Капча сгорает сразу!
        
        if (!$storedCode) {
            return false;
        }
        
        return strtolower($input) === strtolower($storedCode);
    }
}