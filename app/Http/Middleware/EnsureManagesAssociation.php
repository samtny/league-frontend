<?php

namespace App\Http\Middleware;

use App\Association;
use Bouncer;
use Closure;

/**
 * Gates a route on the current user being able to manage the association
 * resolved by ResolveAssociation (which must run first).
 */
class EnsureManagesAssociation
{
    public function handle($request, Closure $next)
    {
        $association = $request->attributes->get('association');

        if (Bouncer::can('manage', $association)) {
            return $next($request);
        }

        return redirect()->route('admin');
    }
}
