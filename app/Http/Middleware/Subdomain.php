<?php

namespace App\Http\Middleware;

use App\Association;
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
        $subdomain = request('association');

        if (!empty($subdomain)) {
            $association = Association::where(['subdomain' => $subdomain])->first();
//var_dump($association->name);exit(1);
            \URL::defaults(['association' => $association]);
        }

        return $next($request);
    }
}
