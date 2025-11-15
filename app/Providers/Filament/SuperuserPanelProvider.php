<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Account;
use App\Filament\Auth\Verification;
use App\Filament\Superuser\Pages\Dashboard;
use App\Http\Middleware\Authenticate;
use App\Providers\Filament\Utils\Middleware;
use App\Providers\Filament\Utils\Navigation;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class SuperuserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('superuser')
            ->path(str(settings('superuser') ?: 'superuser')->slug())
            ->homeUrl('/')
            ->brandName('Clockwork')
            ->brandLogo(fn () => view('banner'))
            ->profile(Account::class)
            ->emailVerification(Verification::class)
            ->colors(['primary' => Color::Cyan])
            ->discoverResources(in: app_path('Filament/Superuser/Resources'), for: 'App\\Filament\\Superuser\\Resources')
            ->discoverPages(in: app_path('Filament/Superuser/Pages'), for: 'App\\Filament\\Superuser\\Pages')
            // ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Superuser/Widgets'), for: 'App\\Filament\\Superuser\\Widgets')
            ->middleware(Middleware::middlewares())
            ->authMiddleware([Authenticate::class])
            ->databaseNotifications()
            ->databaseNotificationsPolling(fn () => '300s')
            ->userMenuItems(Navigation::menuItems());
        // ->spaUrlExceptions(Navigation::spaExceptions())
        // ->spa()
    }
}
