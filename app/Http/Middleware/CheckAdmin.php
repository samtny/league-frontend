<?php

namespace App\Http\Middleware;

use Closure;

class CheckAdmin
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
        if (\Bouncer::can('view-admin-pages')) {
            return $next($request);
        }
        else {
            return redirect()->route('home');
        }
    }
}
