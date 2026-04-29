<?php

use App\Http\Controllers\AllDiariesController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmotionController;
use App\Http\Controllers\EmotionLogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeelingsDiaryController;
use App\Http\Controllers\FoodDiaryController;
use App\Http\Controllers\PersonalDiaryController;
use App\Http\Controllers\PsychologistBookController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupportPhoneController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('/auth')->group(function () {
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login'])->name('login');
  Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::prefix('/user/settings')->middleware(['auth:sanctum', 'blocked'])->group(function () {
  Route::patch('/', [SettingsController::class, 'update']);
  Route::get('/', [SettingsController::class, 'show']);
});

Route::prefix('/user')->group(function () {
  Route::middleware(['auth:sanctum', 'blocked'])->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::patch('/me', [UserController::class, 'update']);
    Route::delete('/me', [UserController::class, 'destroy']);

    Route::prefix('/{model}')->middleware('admin')->group(function () {
      Route::post('/block', [UserController::class, 'block']);
      Route::post('/unblock', [UserController::class, 'unblock']);
      Route::patch('/set-role', [UserController::class, 'setRole']);
    });
  });
  Route::get('/', [UserController::class, 'index']); 
  Route::get('/{model}', [UserController::class, 'show']);
});

Route::prefix('/books')->group(function () {
  Route::middleware(['auth:sanctum', 'blocked', 'psychologist'])->group(function () {
    Route::post('/', [PsychologistBookController::class, 'store']);
    Route::patch('/{book}', [PsychologistBookController::class, 'update']);
    Route::delete('/{book}', [PsychologistBookController::class, 'destroy']);
  });
  Route::get('/', [PsychologistBookController::class, 'index']);
  Route::get('/{book}', [PsychologistBookController::class, 'show']);
});

Route::prefix('/support-phones')->group(function () {
  Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/', [SupportPhoneController::class, 'store']);
    Route::delete('/{phone}', [SupportPhoneController::class, 'destroy']);
    Route::patch('/{phone}', [SupportPhoneController::class, 'update']);
  });
  Route::get('/', [SupportPhoneController::class, 'index']);
  Route::get('/{phone}', [SupportPhoneController::class, 'show']);
});

Route::prefix('/events')->group(function () {
  Route::middleware(['auth:sanctum', 'blocked'])->group(function () {
    Route::post('/', [EventController::class, 'store']);
    Route::patch('/{event}', [EventController::class, 'update']);
    Route::delete('/{event}', [EventController::class, 'destroy']);
  });
  Route::get('/', [EventController::class, 'index']);
  Route::get('/{event}', [EventController::class, 'show']);
});

 Route::prefix('/feelings-diaries')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/trash', [FeelingsDiaryController::class, 'trash']);
        Route::get('/', [FeelingsDiaryController::class, 'index']);
        Route::post('/', [FeelingsDiaryController::class, 'store']);
        Route::post('/{id}/restore', [FeelingsDiaryController::class, 'restore']);
        Route::delete('/{id}/force', [FeelingsDiaryController::class, 'destroy']);
        Route::get('/{diary}', [FeelingsDiaryController::class, 'show']);
        Route::patch('/{diary}', [FeelingsDiaryController::class, 'update']);
        Route::delete('/{diary}', [FeelingsDiaryController::class, 'softDelete']); // Исправлено: без /soft
    });

    // Дневник питания
    Route::prefix('/food-diaries')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/trash', [FoodDiaryController::class, 'trash']);
        Route::get('/', [FoodDiaryController::class, 'index']);
        Route::post('/', [FoodDiaryController::class, 'store']);
        Route::post('/{id}/restore', [FoodDiaryController::class, 'restore']);
        Route::delete('/{id}/force', [FoodDiaryController::class, 'destroy']);
        Route::get('/{diary}/file', [FoodDiaryController::class, 'getFile']);
        Route::get('/{diary}', [FoodDiaryController::class, 'show']);
        Route::patch('/{diary}', [FoodDiaryController::class, 'update']);
        Route::delete('/{diary}', [FoodDiaryController::class, 'softDelete']);
    });

    // Личный дневник
    Route::prefix('/personal-diaries')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/trash', [PersonalDiaryController::class, 'trash']);
        Route::get('/', [PersonalDiaryController::class, 'index']);
        Route::post('/', [PersonalDiaryController::class, 'store']);
        Route::post('/{id}/restore', [PersonalDiaryController::class, 'restore']);
        Route::delete('/{id}/force', [PersonalDiaryController::class, 'destroy']);
        Route::get('/{personalDiary}', [PersonalDiaryController::class, 'show']);
        Route::patch('/{personalDiary}', [PersonalDiaryController::class, 'update']);
        Route::delete('/{personalDiary}', [PersonalDiaryController::class, 'softDelete']);
    });


Route::prefix('/emotions')->group(function () {
  Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/', [EmotionController::class, 'store']);
    Route::patch('/{emotion}', [EmotionController::class, 'update']);
    Route::delete('/{emotion}', [EmotionController::class, 'destroy']);
  });
  Route::get('/', [EmotionController::class, 'index']);
  Route::get('/{emotion}', [EmotionController::class, 'show']);
});

Route::prefix('/emotion-logs')->group(function () {
  Route::middleware(['auth:sanctum', 'blocked'])->group(function () {
    Route::post('/', [EmotionLogController::class, 'store']);
    Route::patch('/{emotionLog}', [EmotionLogController::class, 'update']);
    Route::delete('/{emotionLog}', [EmotionLogController::class, 'destroy']);
    Route::get('/', [EmotionLogController::class, 'index']);
    Route::get('/{emotionLog}', [EmotionLogController::class, 'show']);
  });
});

Route::prefix('/schedules')->group(function () {
  Route::middleware(['auth:sanctum', 'psychologist'])->group(function () {
    Route::post('/', [ScheduleController::class, 'store']);
    Route::post('/generate', [ScheduleController::class, 'generate']);
    Route::delete('/bulk', [ScheduleController::class, 'bulkDestroy']);
    Route::patch('/{schedule}', [ScheduleController::class, 'update']);
    Route::delete('/{schedule}', [ScheduleController::class, 'destroy']);
  });

  Route::get('/', [ScheduleController::class, 'index']);
  Route::get('/{schedule}', [ScheduleController::class, 'show']);
});

Route::prefix('/appointments')->group(function () {
  Route::get('/', [AppointmentController::class, 'index']);
  Route::get('/{appointment}', [AppointmentController::class, 'show']);

  Route::middleware(['auth:sanctum', 'blocked'])->group(function () {
    Route::post('/', [AppointmentController::class, 'store']);
    Route::patch('/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/{appointment}', [AppointmentController::class, 'destroy']);
  });
});

Route::middleware('auth:sanctum')->group(function () {
  Route::get('/all-diaries', [AllDiariesController::class, 'index']);
  Route::get('/all-diaries/trash', [AllDiariesController::class, 'trash']);
});
