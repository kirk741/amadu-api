<?php

namespace App\Providers;

use App\Models\EmotionLog;
use App\Models\FeelingsDiary;
use App\Models\FoodDiary;
use App\Models\PersonalDiary;
use App\Policies\BaseDiaryPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(FeelingsDiary::class, BaseDiaryPolicy::class);
        Gate::policy(PersonalDiary::class, BaseDiaryPolicy::class);
        Gate::policy(FoodDiary::class, BaseDiaryPolicy::class);
        Gate::policy(EmotionLog::class, BaseDiaryPolicy::class);

        Sanctum::getAccessTokenFromRequestUsing(function (Request $request) {
            return $request->query('token') ?? $request->bearerToken();
        });
    }
}
