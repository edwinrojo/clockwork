<?php

namespace App\Filament\Superuser\Resources\ScheduleResource\Pages;

use App\Filament\Superuser\Resources\ScheduleResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchedules extends ListRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CreateAction::make(),
            ]),
        ];
    }
}
