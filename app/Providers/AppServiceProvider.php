<?php

namespace App\Providers;

use App\Services\ScheduleGeneration\MtRng;
use App\Services\ScheduleGeneration\Rng;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
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

        // frontend.scss is linked directly at the top of <head> with nothing
        // else to wait on, so the auto-generated preload tag is redundant and
        // pairs a preload + immediate stylesheet link for the same URL, which
        // can cause a brief unstyled flash in Firefox.
        Vite::usePreloadTagAttributes(function ($src, $url, $chunk, $manifest) {
            return $src === 'resources/sass/frontend.scss' ? false : [];
        });
    }
}
