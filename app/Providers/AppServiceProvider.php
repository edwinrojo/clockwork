<?php

namespace App\Providers;

use App\Drivers\FakeFilesystemAdapter;
use App\Models\Token;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Forms\Components\Select;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        App::bind(LoginResponse::class, \App\Http\Responses\LoginResponse::class);

        App::bind(LogoutResponse::class, \App\Http\Responses\LogoutResponse::class);

        Sanctum::usePersonalAccessTokenModel(Token::class);

        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_START, fn () => Blade::render('@vite(\'resources/css/blade.css\')'));

        // Select::configureUsing(fn (Select $select) => $select->native(false));

        Table::configureUsing(fn (Table $table) => $table->paginated([10, 25, 50, 100])->defaultPaginationPageOption(25)->striped()->selectCurrentPageOnly(true));

        TrashedFilter::configureUsing(fn (TrashedFilter $filter) => $filter->native(false));

        Notifications::verticalAlignment(VerticalAlignment::End);

        Notifications::alignment(Alignment::Start);

        FilamentIcon::register(['panels::user-menu.logout-button' => 'gmdi-logout-o', 'panels::user-menu.profile-item' => 'gmdi-account-circle-o']);

        Storage::extend('fake', function (Application $app, array $config) {
            $adapter = new FakeFilesystemAdapter;

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
