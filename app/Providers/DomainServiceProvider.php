<?php

namespace App\Providers;

use App\Association;
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
        View::share('subdomain', Association::subdomainFromHost(request()->getHost()));
    }
}
