<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('/auth')->group(function () {
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login'])->name('login');

  Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
  });
});

Route::middleware('blocked')->group(function () {
  Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/user')->group(function () {
      Route::prefix('/me')->group(function () {
        Route::get('/', [UserController::class, 'me']);
        Route::patch('/', [UserController::class, 'update']);
        Route::delete('/', [UserController::class, 'destroy']);
      });

      Route::get('/', [UserController::class, 'index']);
      Route::get('/{model}', [UserController::class, 'show']);

      Route::prefix('/{model}')->middleware('admin')->group(function () {
        Route::post('/block', [UserController::class, 'block']);
        Route::post('/unblock', [UserController::class, 'unblock']);
        Route::patch('/set-role', [UserController::class, 'setRole']);
      });
    });
  });
});
