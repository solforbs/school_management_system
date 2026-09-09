<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns off the handful of actions that shouldn't run inside the shared
 * demo school - anything that spends real money, talks to real hardware,
 * sends real messages, or changes the shared login itself. Everything else
 * (add a student, edit a subject, enter marks) stays fully editable; that's
 * the point of the demo.
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
