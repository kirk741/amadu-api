<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBlocked
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->is_blocked) {
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Ваш аккаунт заблокирован. Сессия завершена.'
            ], 403);
        }

        return $next($request);
    }
}
