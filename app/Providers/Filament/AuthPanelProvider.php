<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\Recover;
use App\Filament\Auth\Reset;
use App\Filament\Auth\Verification;
use App\Http\Middleware\Authenticate;
use App\Http\Responses\LoginResponse;
use App\Models\Employee;
use App\Models\Social;
use App\Models\User;
use App\Providers\Filament\Utils\Middleware;
use App\Providers\Filament\Utils\Navigation;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Exception;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('auth')
            ->path('auth')
            ->homeUrl('/')
            ->brandName('Clockwork')
            ->brandLogo(fn () => view('banner'))
            ->login(Login::class)
            ->revealablePasswords(false)
            ->emailVerification(Verification::class)
            ->passwordReset(Reset::class, Recover::class)
            ->colors(['primary' => Color::Cyan])
            ->pages([Redirect::class])
            ->middleware(Middleware::middlewares())
            ->authMiddleware([Authenticate::class])
            ->databaseNotifications()
            ->databaseNotificationsPolling(fn () => '300s')
            ->userMenuItems(Navigation::menuItems())
            ->plugin(
                FilamentSocialitePlugin::make()
                    ->socialiteUserModelClass(Social::class)
                    ->registration(fn (?Authenticatable $user) => (bool) $user)
                    ->resolveUserUsing(function ($oauthUser) {
                        $model = match (session()->get('guard')) {
                            'employee' => Employee::class,
                            default => User::class,
                        };

                        /** @var User|Employee $user */
                        $user = $model::where('email', $oauthUser->getEmail())->first();

                        if ($user) {
                            $user->markEmailAsVerified();
                        }

                        return $user;
                    })
                    ->providers(array_map(function (string $provider) {
                        return Provider::make($provider)
                            ->label(ucfirst($provider))
                            ->icon("fab-$provider")
                            ->outlined(true)
                            ->hidden();
                    }, config('services.oauth_providers')))
            );
    }
}

class Redirect extends Dashboard
{
    public function __construct()
    {
        (new LoginResponse)->toResponse(request());
    }

    public function mount(): void
    {
        (new LoginResponse)->toResponse(request());
    }
}
