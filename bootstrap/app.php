<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PsychologistMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            'psychologist' => PsychologistMiddleware::class,
            'admin' => AdminMiddleware::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Войдите в аккаунт для просмотра',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        });
        $exceptions->render(function (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Нет доступа к этому ресурсу',
                'error_code' => 'FORBIDDEN'
            ], 403);
        });
        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422);
        });
        $exceptions->render(function (NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Страница не найдена',
                'error_code' => 'NOT_FOUND'
            ], 404);
        });
        $exceptions->render(function (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ресурс не найден',
                'error_code' => 'NOT_FOUND'
            ], 404);
        });
        $exceptions->render(function (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Ошибка HTTP',
                'error_code' => 'HTTP_ERROR'
            ], $e->getStatusCode());
        });
        $exceptions->render(function (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка базы данных',
                'error_code' => 'DB_ERROR'
            ], 500);
        });
        $exceptions->render(function (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
                'error_code' => 'SERVER_ERROR'
            ], 500);
        });
    })->create();
