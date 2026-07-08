<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * Content-Security-Policy is deliberately not set here: the app has
     * several inline onclick= handlers (e.g. components/footer.blade.php,
     * forms/results/choose-*.blade.php) and external scripts (TinyMCE CDN,
     * Google Tag Manager) that a real CSP would need to account for first.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
