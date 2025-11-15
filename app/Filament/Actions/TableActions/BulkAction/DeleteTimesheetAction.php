<?php

namespace App\Filament\Actions\TableActions\BulkAction;

use App\Models\Timesheet;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class DeleteTimesheetAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name ??= 'delete-timesheets';

        $this->requiresConfirmation();

        $this->icon('heroicon-o-trash');

        $this->groupedIcon('heroicon-o-trash');

        $this->modalIcon('heroicon-o-trash');

        $this->modalDescription('This will permanently delete the selected employee\'s timesheets for the selected month.');

        $this->color('danger');

        $this->form([
            TextInput::make('month')
                ->markAsRequired()
                ->rule('required')
                ->type('month'),
            TextInput::make('password')
                ->markAsRequired()
                ->rule('required')
                ->currentPassword()
                ->type('password'),
        ]);

        $this->action(function (Collection $records, array $data) {
            Timesheet::query()
                ->whereIn('employee_id', $records->pluck('id'))
                ->whereDate('month', $data['month'].'-01')
                ->delete();

            Notification::make()
                ->danger()
                ->title('Timesheets for the selected employees of the month '.$data['month'].' have been deleted.')
                ->send();
        });
    }
}
