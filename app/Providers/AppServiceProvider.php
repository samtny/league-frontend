<?php

namespace App\Providers;

use App\Services\ScheduleGeneration\MtRng;
use App\Services\ScheduleGeneration\Rng;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(Rng::class, MtRng::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // laravel 7 upgrade docs:
        Blade::withoutComponentTags();
    }
}
