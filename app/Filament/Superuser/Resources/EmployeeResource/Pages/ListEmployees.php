<?php

namespace App\Filament\Superuser\Resources\EmployeeResource\Pages;

use App\Filament\Superuser\Resources\EmployeeResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CreateAction::make(),
            ]),
        ];
    }
}
