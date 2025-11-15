<?php

namespace App\Filament\Superuser\Resources\OfficeResource\Pages;

use App\Filament\Superuser\Resources\OfficeResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOffices extends ListRecords
{
    protected static string $resource = OfficeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CreateAction::make(),
            ]),
        ];
    }
}
