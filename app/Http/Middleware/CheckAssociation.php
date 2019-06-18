<?php

namespace App\Http\Middleware;

use Bouncer;
use Closure;
use App\Association;

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

        if (!($association instanceof Association)) {
            // FIXME: redo routes so we always get association from there instead:
            $subdomain = array_first(explode('.', $request->getHost()));

            $association = Association::where('subdomain', $subdomain)->first();
        }

        if (Bouncer::can('manage', $association)) {
            return $next($request);
        }
        else {
            return redirect()->route('admin');
        }
    }
}
