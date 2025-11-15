<?php

namespace App\Filament\Secretary\Resources\OfficeResource\Pages;

use App\Filament\Secretary\Resources\OfficeResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;

class ListOffices extends ListRecords
{
    protected static string $resource = OfficeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
            ]),
        ];
    }
}
