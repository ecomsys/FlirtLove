<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\URL;

class MakeViteUrlsRelative
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->headers->get('X-Forwarded-Host') ?: $request->getHost();
        
        // Если запрос пришел с туннеля (не локалхост)
        if (app()->environment('local') && !in_array($host, ['localhost', '127.0.0.1', '[::1]'])) {
            // Говорим Ларавелу генерировать ВСЕ ссылки через HTTPS (починит Debugbar и Livewire)
            URL::forceScheme('https');
        }

        $response = $next($request);

        // Если это HTML-страница, заменяем адреса Vite
        if (app()->environment('local') && str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            if (!in_array($host, ['localhost', '127.0.0.1', '[::1]'])) {
                $content = $response->getContent();
                // Заменяем внутренний адрес Vite на адрес туннеля
                $tunnelHost = 'https://' . $host;
                $content = preg_replace('/http:\/\/(?:localhost|127\.0\.0\.1|\[::1\]):5173/', $tunnelHost, $content);
                $response->setContent($content);
            }
        }
        
        return $response;
    }
}