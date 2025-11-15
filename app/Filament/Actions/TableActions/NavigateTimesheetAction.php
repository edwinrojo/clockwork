<?php

namespace App\Filament\Actions\TableActions;

use App\Enums\TimesheetPeriod;
use App\Models\Timesheet;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;

class NavigateTimesheetAction extends Action
{
    protected function setUp(): void
    {
        $query = fn ($month) => Timesheet::query()
            ->whereColumn('timesheets.id', 'timesheets.timesheet_id')
            ->where('timesheets.month', $month)
            ->where('timesheets.employee_id', Auth::id());

        parent::setUp();

        $this->name('navigate-timesheet');

        $this->label('Navigate');

        $this->icon('gmdi-document-scanner-o');

        $this->modalIcon('gmdi-document-scanner-o');

        $this->modalWidth('sm');

        $this->modalSubmitActionLabel('Navigate');

        $this->modalDescription('Navigate to your timesheet for the selected month.');

        $this->schema([
            TextInput::make('month')
                ->type('month')
                ->rule('required')
                ->markAsRequired()
                ->rule(fn () => function ($attribute, $value, $fail) use ($query) {
                    if (isset($this->record) && $this->record->month === $value) {
                        $fail('Already navigated here.');
                    }

                    if ($query("{$value}-01")->doesntExist()) {
                        $fail('No records found for the selected month.');
                    }
                }),
            Select::make('period')
                ->options(TimesheetPeriod::class)
                ->default(TimesheetPeriod::FULL)
                ->required(),
        ]);

        $this->action(function (array $data) use ($query) {
            $timesheet = $query("{$data['month']}-01")->first();

            return redirect()->route('filament.employee.resources.timesheets.view', [
                'record' => $timesheet->id,
                'filters' => [
                    'period' => $data['period'] ?? null,
                ],
            ]);
        });
    }
}
