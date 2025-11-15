<?php

namespace App\Filament\Superuser\Resources\GroupResource\Pages;

use App\Filament\Superuser\Resources\GroupResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CreateAction::make(),
            ]),
        ];
    }
}
