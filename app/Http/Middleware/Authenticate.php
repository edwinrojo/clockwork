<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as Middleware;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        $id = $panel?->getId();

        $current = Auth::guard($id === 'employee' ? 'employee' : null);

        $auth = Auth::guard($id === 'employee' ? null : 'employee');

        if (! $current->check() && ! $auth->check()) {
            if (empty($guards)) {
                $guards = [null];
            }

            foreach ($guards as $guard) {
                if ($this->auth->guard($guard)->check()) {
                    $this->auth->shouldUse($guard);

                    return;
                }
            }

            $this->unauthenticated($request, $guards);

            return;
        }

        if ($current->check() || $auth->check()) {
            $this->auth->shouldUse(Filament::getAuthGuard());
        }

        /** @var Model $user */
        $user = Filament::auth()->user();

        abort_if($user instanceof FilamentUser && ! $user->canAccessPanel($panel), 403);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo($request): ?string
    {
        return $request->expectsJson() ? null : route('filament.auth.auth.login');
    }
}
