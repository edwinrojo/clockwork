<?php

namespace App\Filament\Developer\Resources\RouteResource\Pages;

use App\Filament\Developer\Resources\RouteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoute extends EditRecord
{
    protected static string $resource = RouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
