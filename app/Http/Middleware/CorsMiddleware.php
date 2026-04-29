<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Список разрешенных доменов
        $allowedOrigins = [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'https://amadu-frontend.vercel.app' // Твой Vercel без слеша
        ];

        $origin = $request->header('Origin');

        // Если домен в списке, разрешаем его
        if (in_array($origin, $allowedOrigins)) {
            
            // Если это запрос OPTIONS (Preflight), отвечаем сразу 204
            if ($request->isMethod('OPTIONS')) {
                return response('', 204)
                    ->header('Access-Control-Allow-Origin', $origin)
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
                    ->header('Access-Control-Allow-Credentials', 'true');
            }

            $response = $next($request);

            // Добавляем заголовки к основному ответу
            if (method_exists($response, 'header')) {
                return $response
                    ->header('Access-Control-Allow-Origin', $origin)
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
                    ->header('Access-Control-Allow-Credentials', 'true');
            }
            
            return $response;
        }

        return $next($request);
    }
}
