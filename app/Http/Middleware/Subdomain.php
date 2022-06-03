<?php

namespace App\Http\Middleware;

use Closure;
use App\Association;
use Illuminate\Support\Arr;

class Subdomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        \URL::defaults(['subdomain' => request('subdomain')]);

        $association = $request->route('association');

        if (!($association instanceof Association)) {
            // FIXME: redo routes so we always get association from there instead:
            $subdomain = Arr::first(explode('.', $request->getHost()));

            $association = Association::where('subdomain', $subdomain)->first();
        }

        \View::share('association', $association);

        return $next($request);
    }
}
