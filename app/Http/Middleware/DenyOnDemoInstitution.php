<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns off the few actions that must not run inside the shared demo
 * school: paying for a subscription (real money) and editing the shared
 * demo login itself. Everything else a Director can do - students, marks,
 * devices, notices (delivery is simulated there) - stays open; that's the
 * point of the demo.
 */
class DenyOnDemoInstitution
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            currentInstitution()?->is_demo === true,
            403,
            'That action is turned off in the demo school.',
        );

        return $next($request);
    }
}
