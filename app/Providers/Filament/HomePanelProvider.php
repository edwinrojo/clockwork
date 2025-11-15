<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Responses\LoginResponse;
use App\Models\Employee;
use App\Models\Social;
use App\Models\User;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class HomePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('home')
            ->path('')
            ->homeUrl('/')
            ->default()
            ->brandName('Clockwork')
            ->brandLogo(fn () => view('banner'))
            ->colors(['primary' => Color::Cyan])
            ->discoverPages(in: app_path('Filament/Home/Pages'), for: 'App\\Filament\\Home\\Pages')
            ->navigation(false)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugin(
                FilamentSocialitePlugin::make()
                    ->socialiteUserModelClass(Social::class)
                    ->registration(fn (?Authenticatable $user) => (bool) $user)
                    ->redirectAfterLoginUsing(fn () => app(LoginResponse::class)->toResponse(request()))
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
