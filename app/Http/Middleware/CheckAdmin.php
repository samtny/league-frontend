<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (\Bouncer::can('view-admin-pages')) {
            return $next($request);
        } else {
            return redirect('/');
        }
    }
}
