<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OauthAuthorization
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guards = [
            'web' => Auth::check(),
            'employee' => Auth::guard('employee')->check(),
        ];

        foreach ($guards as $guard => $authenticated) {
            if (! $authenticated) {
                continue;
            }

            if (str_contains($request->path(), 'callback')) {
                abort_if(session()->get('guard') !== $guard || ! (bool) session()->get('oauth-link'), 403);
            } else {
                abort_if(request()->input('guard') !== $guard || ! (bool) request()->input('link'), 403);
            }
        }

        return $next($request);
    }
}
