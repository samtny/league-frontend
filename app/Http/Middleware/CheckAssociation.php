<?php

namespace App\Http\Middleware;

use Bouncer;
use Closure;

class CheckAssociation
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
        $association = $request->route('association');

        if (Bouncer::can('manage', $association)) {
            return $next($request);
        }
        else {
            return redirect()->route('admin');
        }
    }
}
