<?php

namespace App\Filament\Secretary\Resources\ScheduleResource\Pages;

use App\Filament\Secretary\Resources\ScheduleResource;
use Filament\Actions;
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
                // Actions\Action::make('new_overtime_schedule')
                //     ->icon('heroicon-m-plus')
                //     ->url(route('filament.secretary.resources.schedules.create-overtime')),
            ]),
        ];
    }
}
