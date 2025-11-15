<?php

namespace App\Providers;

use App\Drivers\FakeFilesystemAdapter;
use App\Models\Token;
use Exception;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Forms\Components\Select;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Assets\Css;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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

        try {
            $manifestPath = public_path('build/manifest.json');
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                if (isset($manifest['resources/css/blade.css']['file'])) {
                    $bladeAssetPath = public_path('build/'.$manifest['resources/css/blade.css']['file']);
                    if (file_exists($bladeAssetPath)) {
                        $bladeAsset = $bladeAssetPath;
                    } else {
                        $bladeAsset = Vite::asset('resources/css/blade.css');
                    }
                } else {
                    $bladeAsset = Vite::asset('resources/css/blade.css');
                }
            } else {
                $bladeAsset = Vite::asset('resources/css/blade.css');
            }
        } catch (Exception $e) {
            $bladeAsset = Vite::asset('resources/css/blade.css');
        }

        FilamentAsset::register([Css::make('app', __DIR__.'/../../resources/css/app.css'), Css::make('blade', $bladeAsset)]);

        Select::configureUsing(fn (Select $select) => $select->native(false));

        Table::configureUsing(fn (Table $table) => $table->paginated([10, 25, 50, 100])->defaultPaginationPageOption(25)->striped());

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
