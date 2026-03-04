<?php

namespace App\Providers;

use App\Models\FeelingsDiary;
use App\Models\FoodDiary;
use App\Models\PersonalDiary;
use App\Policies\BaseDiaryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
    }
}
