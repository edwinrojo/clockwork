<?php

namespace App\Filament\Superuser\Pages;

use App\Filament\Actions\ImportDataAction;
use Filament\Pages\Dashboard as Home;

class Dashboard extends Home
{
    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-home-o';

    protected function getHeaderActions(): array
    {
        return [
            ImportDataAction::make(),
        ];
    }
}
