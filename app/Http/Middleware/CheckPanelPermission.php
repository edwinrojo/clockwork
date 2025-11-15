<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPanelPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|Employee */
        $user = (Auth::guard()->user() ?? Auth::guard('employee')->user());

        abort_unless($user?->canAccessPanel(Filament::getCurrentOrDefaultPanel()) ?? true, 403);

        return $next($request);
    }
}
