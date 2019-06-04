<?php

namespace App\Http\Middleware;

use Closure;

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

        return $next($request);
    }
}
