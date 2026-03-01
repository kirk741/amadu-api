<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckBlocked;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'blocked' => CheckBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не авторизован',
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещён',
            ], 403);
        });

        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        });
    })->create();
