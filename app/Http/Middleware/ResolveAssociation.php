<?php

namespace App\Http\Middleware;

use App\Association;
use Closure;

/**
 * Resolves "the current association" one way, for every route that needs
 * it: an explicit {association} route-model-bound parameter if the route
 * has one, otherwise the subdomain of the request host. Makes the result
 * available uniformly downstream via the `association` request attribute
 * and (for views rendered without it being passed explicitly) View::share.
 *
 * This is a resolution step only - it does not authorize anything. Routes
 * that need to gate on the resolved association should also apply
 * EnsureManagesAssociation.
 */
class ResolveAssociation
{
    public function handle($request, Closure $next)
    {
        \URL::defaults(['subdomain' => request('subdomain')]);

        $association = Association::resolveForRequest($request);

        $request->attributes->set('association', $association);

        \View::share('association', $association);

        return $next($request);
    }
}
