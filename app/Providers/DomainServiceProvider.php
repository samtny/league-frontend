<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // FIXME: redo routes so we always get association from there instead:
        $subdomain = array_first(explode('.', request()->getHost()));

        View::share('subdomain', $subdomain);
    }
}
