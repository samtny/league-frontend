<?php

namespace App\Http\Middleware;

use App\Association;
use Bouncer;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Gates a route on the current user being able to manage the association
 * resolved by ResolveAssociation (which must run first).
 *
 * Some admin routes (series/schedule/results) don't carry an {association}
 * segment of their own - see the FIXME comments in routes/web.php - so
 * ResolveAssociation falls back to the request's host subdomain for them,
 * which need not be the association that actually owns the {series}/
 * {schedule} being managed. Before trusting that fallback for
 * authorization, prefer deriving the owning association directly from
 * whichever resource model the route did bind.
 */
class EnsureManagesAssociation
{
    private const OWNING_MODEL_PARAMS = ['schedule', 'series'];

    public function handle($request, Closure $next)
    {
        $association = $request->attributes->get('association');

        if (!($association instanceof Association)) {
            $association = $this->resolveFromOwningModel($request);
        }

        if (Bouncer::can('manage', $association)) {
            return $next($request);
        }

        return redirect()->route('admin');
    }

    private function resolveFromOwningModel($request): ?Association
    {
        foreach (self::OWNING_MODEL_PARAMS as $param) {
            $model = $request->route($param);

            if ($model instanceof Model && method_exists($model, 'association')) {
                return $model->association;
            }
        }

        return null;
    }
}
