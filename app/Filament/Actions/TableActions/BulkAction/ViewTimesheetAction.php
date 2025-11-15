<?php

namespace App\Filament\Actions\TableActions\BulkAction;

use App\Models\Employee;
use App\Models\Timesheet;
use Filament\Actions\BulkAction;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ViewTimesheetAction extends BulkAction
{
    protected bool $listing = false;

    public static function make(?string $name = null, bool $listing = false): static
    {
        $class = static::class;

        $name ??= ($listing ? 'bulk-view-timesheet' : 'bulk-view-timesheet-form');

        $static = app($class, ['name' => $name]);

        $static->listing = $listing;

        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->listing) {
            $this->extraAttributes(['class' => 'hidden']);

            $this->icon('gmdi-document-scanner-o');

            $this->modalHeading('View timesheets');

            $this->modalWidth(fn ($livewire) => match ($livewire->mountedTableBulkActionData['format']) {
                'default' => '3xl',
                'preformatted' => 'md',
                'csc' => 'xl',
            });

            $this->modalSubmitAction(false);

            $this->modalContent(function ($records, $livewire) {
                $data = $livewire->mountedTableBulkActionData;

                $month = Carbon::parse($data['month']);

                if ($data['format'] === 'csc') {
                    $timesheets = Timesheet::query()
                        ->whereHas('employee', fn ($query) => $query->whereIn('id', $records->pluck('id')))
                        ->whereDate('month', $month->startOfMonth())
                        ->when($data['period'] === '1st', fn ($query) => $query->with('firstHalf'))
                        ->when($data['period'] === '2nd', fn ($query) => $query->with('secondHalf'))
                        ->when($data['period'] === 'regular', fn ($query) => $query->with('regularDays'))
                        ->when($data['period'] === 'overtime', fn ($query) => $query->with('overtimeWork'))
                        ->with(['employee:id,name,status'])
                        ->orderBy(Employee::select('full_name')->whereColumn('employees.id', 'timesheets.employee_id')->limit(1))
                        ->lazy();

                    $timesheets = match ($data['period']) {
                        '1st' => $timesheets->map->setFirstHalf(),
                        '2nd' => $timesheets->map->setSecondHalf(),
                        'overtime' => $timesheets->map->setOvertimeWork(),
                        'regular' => $timesheets->map->setRegularDays(),
                        'dates' => $timesheets->map->setCustomDates(collect($data['dates'])->flatten()->toArray()),
                        'range' => $timesheets->map->setCustomRange(Carbon::parse($data['from'])->day, Carbon::parse($data['to'])->day),
                        default => $timesheets,
                    };

                    $timesheets = $timesheets->map->setTemporary();

                    return View::make('print.csc')->viewData([
                        'preview' => true,
                        'timesheets' => $timesheets,
                    ]);
                }

                $from = match ($data['period']) {
                    '2nd' => $month->clone()->setDay(16)->startOfDay(),
                    'range' => Carbon::parse($data['from'])->startOfDay(),
                    'dates' => null,
                    default => $month->clone()->startOfMonth(),
                };

                $to = match ($data['period']) {
                    '1st' => $month->clone()->setDay(15)->endOfDay(),
                    'range' => Carbon::parse($data['to'])->endOfDay(),
                    'dates' => null,
                    default => $month->clone()->endOfMonth(),
                };

                $timelogs = function ($query) use ($data, $from, $to) {
                    if ($data['period'] === 'dates') {
                        $query->where(function ($query) use ($data) {
                            foreach ($data['dates'] as $date) {
                                $query->orWhereDate('time', $date);
                            }
                        });
                    } else {
                        $query->whereBetween('time', [$from->subDay(), $to->addDay()]);
                    }
                };

                return View::make($data['format'] === 'preformatted' ? 'print.preformatted' : 'print.default')->viewData([
                    'preview' => true,
                    'month' => $month,
                    'period' => $data['period'],
                    'from' => $data['period'] !== 'dates' ? $from->day : null,
                    'to' => $data['period'] !== 'dates' ? $to->day : null,
                    'dates' => $data['period'] === 'dates' ? collect($data['dates'])->flatten()->sort()->values()->toArray() : null,
                    'employees' => $records->load([
                        'timelogs' => $timelogs,
                        'scanners' => fn ($query) => $query->reorder()->orderBy('priority', 'desc')->orderBy('name'),
                        'timelogs.scanner',
                    ]),
                ]);
            });

            $this->modalCancelActionLabel('Close');

            $this->modalFooterActionsAlignment('end');
        } else {
            $this->name = 'view-timesheet-form';

            $this->requiresConfirmation();

            $this->color('gray');

            $this->modalHeading('View timesheets');

            $this->modalDescription(fn (Collection $records) => "View timesheets of {$records->count()} selected ".str('employee')->plural($records->count()).'.');

            $this->modalIcon('gmdi-document-scanner-o');

            $this->label('View Timesheets');

            $this->icon('gmdi-document-scanner-o');

            $this->form(app(ExportTimesheetAction::class, ['name' => 'ex'])->exportForm(preview: true));

            $this->action(fn ($records, $livewire) => $livewire->replaceMountedTableBulkAction('bulk-view-timesheet', $records->toArray()));
        }

        $this->slideOver();
    }
}
