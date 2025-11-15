<?php

namespace App\Filament\Superuser\Resources\TimesheetResource\Pages;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentSubstatus;
use App\Enums\TimelogMode;
use App\Enums\TimelogState;
use App\Filament\Actions\ExportAttendanceAction;
use App\Filament\Actions\PreselectFormAction;
use App\Filament\Actions\TableActions\BulkAction\DeleteTimesheetAction;
use App\Filament\Actions\TableActions\BulkAction\ExportTimesheetAction;
use App\Filament\Actions\TableActions\BulkAction\ExportTransmittalAction;
use App\Filament\Actions\TableActions\BulkAction\GenerateTimesheetAction;
use App\Filament\Actions\TableActions\BulkAction\ViewTimesheetAction;
use App\Filament\Actions\TableActions\UpdateEmployeeAction;
use App\Filament\Actions\TimelogsActionGroup;
use App\Filament\Filters\OfficeFilter;
use App\Filament\Superuser\Resources\TimesheetResource;
use App\Jobs\ProcessAffectedTimetables;
use App\Jobs\ProcessTimesheet;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Timelog;
use Filament\Forms;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListTimesheets extends ListRecords
{
    use HasFiltersAction;

    public string $action = 'hide';

    public $timelogs;

    protected static string $resource = TimesheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAttendanceAction::make(),
            TimelogsActionGroup::make(),
            PreselectFormAction::make()
                ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class),
            // FilterAction::make()
            //     ->label('Config')
            //     ->icon('heroicon-o-cog-6-tooth')
            //     ->modalHeading('Config')
            //     ->slideOver(false)
            //     ->form([
            //         Forms\Components\Select::make('model')
            //             ->live()
            //             ->placeholder('List to show')
            //             ->default(Employee::class)
            //             ->required()
            //             ->options([Employee::class => 'Employee', Office::class => 'Office', Group::class => 'Group']),
            //     ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ($this->filters['model'] ?? Employee::class)::query()->withoutGlobalScopes(['excludeInterns']))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('offices.code')
                    ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class)
                    ->searchable()
                    ->formatStateUsing(function (Employee $record) {
                        $offices = $record->offices->map(function ($office) {
                            return str($office->code)
                                ->when($office->pivot->current, function ($code) {
                                    return <<<HTML
                                        <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--primary-400);--c-600:var(--primary-600);">$code</span>
                                    HTML;
                                });
                        })->join(', ');

                        return str($offices)->toHtmlString();
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->toggleable()
                    ->limit(24)
                    ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class)
                    ->getStateUsing(function (Employee $employee): string {
                        return str($employee->status?->value)
                            ->title()
                            ->when($employee->substatus?->value, function ($status) use ($employee) {
                                return $status->append(" ({$employee->substatus->value})")->replace('_', '-')->title();
                            });
                    }),
                Tables\Columns\TextColumn::make('groups.name')
                    ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('status')
                    ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class)
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options(EmploymentStatus::class)
                            ->placeholder('All')
                            ->multiple()
                            ->searchable(),
                        Forms\Components\Select::make('substatus')
                            ->visible(function (callable $get) {
                                $visibleOn = [
                                    EmploymentStatus::CONTRACTUAL->value,
                                ];

                                return count(array_diff($visibleOn, $get('status') ?? [])) < count($visibleOn);
                            })
                            ->options(EmploymentSubstatus::class)
                            ->placeholder('All')
                            ->multiple()
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! isset($data['status'])) {
                            return;
                        }

                        $query->when(
                            in_array(EmploymentStatus::INTERNSHIP->value, $data['status']),
                            fn ($query) => $query->withoutGlobalScope('excludeInterns'),
                        );

                        $query->when(
                            $data['status'],
                            fn ($query) => $query->whereIn('status', $data['status'])
                        );

                        $query->when(
                            $data['substatus'],
                            fn ($query) => $query->whereIn('substatus', $data['substatus'])
                        );
                    })
                    ->indicateUsing(function (array $data) {
                        $indicators = [];

                        if (isset($data['status']) && count($data['status'])) {
                            $statuses = collect($data['status'])
                                ->map(fn ($status) => EmploymentStatus::tryFrom($status)?->getLabel());

                            $indicators[] = Indicator::make('Status: '.$statuses->join(', '))->removeField('status');
                        }

                        if (isset($data['substatus']) && count($data['substatus'])) {
                            $substatuses = collect($data['substatus'])
                                ->map(fn ($status) => EmploymentSubstatus::tryFrom($status)->getLabel());

                            $indicators[] = Indicator::make('Substatus: '.$substatuses->join(', '))->removeField('substatus');
                        }

                        return $indicators;
                    }),
                OfficeFilter::make(),
                Tables\Filters\SelectFilter::make('groups')
                    ->relationship('groups', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    UpdateEmployeeAction::make()
                        ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class),
                    Tables\Actions\Action::make('export')
                        ->label('Export')
                        ->requiresConfirmation()
                        ->icon('heroicon-o-document-arrow-down')
                        ->modalIcon('heroicon-o-document-arrow-down')
                        ->modalDescription(fn (Employee $record) => "Export timesheet of {$record->name}")
                        ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class)
                        ->form(fn () => app(ExportTimesheetAction::class, ['name' => null])->exportForm())
                        ->action(fn (Employee $record, array $data) => app(ExportTimesheetAction::class, ['name' => null])->exportAction($record, $data)),
                    Tables\Actions\Action::make('generate')
                        ->icon('heroicon-o-bolt')
                        ->requiresConfirmation()
                        ->modalDescription(app(GenerateTimesheetAction::class, ['name' => null])->generateConfirmation())
                        ->successNotificationTitle(fn ($record) => "Timesheet for {$record->name} generated.")
                        ->form(app(GenerateTimesheetAction::class, ['name' => null])->generateForm())
                        ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class)
                        ->action(function (Employee $record, Tables\Actions\Action $component, array $data) {
                            $user = Auth::user();

                            if ($user->superuser && $user->developer && ! empty($data) && $data['month'] === $data['password']) {
                                $this->replaceMountedTableAction('thaumaturge', $record->id, ['month' => $data['month']]);

                                return;
                            }

                            ProcessTimesheet::dispatchSync($record, $data['month']);

                            $component->sendSuccessNotification();
                        }),
                    Tables\Actions\Action::make('thaumaturge')
                        ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class && Auth::user()->superuser && Auth::user()->developer)
                        ->extraAttributes(['class' => 'hidden'])
                        ->modalHeading(fn ($record) => $record->name)
                        ->modalAlignment('center')
                        ->icon('heroicon-o-puzzle-piece')
                        ->modalIcon('heroicon-o-puzzle-piece')
                        ->modalDescription(null)
                        ->modalWidth('3xl')
                        ->slideOver()
                        ->successNotificationTitle(fn ($record) => "Timesheet for {$record->name} generated.")
                        ->form(function ($arguments, $record) {
                            $month = Carbon::parse($arguments['month']);

                            $from = $month->clone()->startOfMonth();

                            $to = $month->clone()->endOfMonth();

                            $this->timelogs = $record->timelogs()->whereBetween('time', [$from, $to])->withoutGlobalScopes()->get();

                            return [
                                Forms\Components\TextInput::make('month')
                                    ->default($month->format('Y-m'))
                                    ->hidden()
                                    ->dehydratedWhenHidden(),
                                Forms\Components\Tabs::make()
                                    // ->contained(false)
                                    ->tabs([
                                        Forms\Components\Tabs\Tab::make('Timelogs')
                                            ->schema([
                                                Forms\Components\View::make('print.timelogs')
                                                    ->viewData([
                                                        'employee' => $record,
                                                        'timelogs' => $this->timelogs,
                                                        'preview' => true,
                                                        'month' => $month,
                                                        'from' => $from->format('Y-m-d'),
                                                        'to' => $to->format('Y-m-d'),
                                                        'action' => $this->action,
                                                    ]),
                                            ]),
                                        Forms\Components\Tabs\Tab::make('New')
                                            ->schema([
                                                Forms\Components\Repeater::make('timelogs')
                                                    ->hiddenLabel()
                                                    ->collapsible()
                                                    ->cloneable()
                                                    ->reorderable(false)
                                                    ->columns(2)
                                                    ->defaultItems(0)
                                                    ->addActionLabel('Create')
                                                    ->schema(function (Employee $record) use ($from, $to) {
                                                        $scanners = $record->scanners()->whereNotNull('scanners.uid')->get();

                                                        return [
                                                            Forms\Components\Select::make('device')
                                                                ->options($scanners->pluck('name', 'uid')->toArray())
                                                                ->required()
                                                                ->afterStateUpdated(function (int $state, Forms\Set $set) use ($scanners) {
                                                                    $set('uid', $scanners->first(fn ($scanner) => $scanner->uid === $state)?->pivot->uid);
                                                                }),
                                                            Forms\Components\DateTimePicker::make('time')
                                                                ->distinct()
                                                                ->required()
                                                                ->minDate($from->format('Y-m-d H:i:s'))
                                                                ->maxDate($to->format('Y-m-d H:i:s')),
                                                            Forms\Components\Select::make('state')
                                                                ->options(TimelogState::class)
                                                                ->default(TimelogState::CHECK_IN)
                                                                ->required(),
                                                            Forms\Components\Select::make('mode')
                                                                ->options(function () {
                                                                    return collect(TimelogMode::cases())->mapWithKeys(fn ($mode) => [
                                                                        $mode->value => $mode->getLabel(true),
                                                                    ]);
                                                                })
                                                                ->default(TimelogMode::FINGERPRINT_1)
                                                                ->required(),
                                                            Forms\Components\Hidden::make('pseudo')
                                                                ->default(true),
                                                            Forms\Components\Hidden::make('uid')
                                                                ->default(null),
                                                        ];
                                                    }),
                                            ]),
                                    ]),
                            ];
                        })
                        ->action(function (Employee $record, Tables\Actions\Action $component, array $data) {
                            Timelog::upsert($data['timelogs'], ['time', 'device', 'uid', 'mode', 'state'], ['uid', 'time', 'state', 'mode']);

                            ProcessTimesheet::dispatchSync($record, Carbon::parse($data['month'] ?? today()->startOfMonth()));

                            $component->sendSuccessNotification();

                            $component->halt();
                        }),

                ]),
            ])
            ->bulkActions([
                ViewTimesheetAction::make(listing: true)
                    ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class),
                ViewTimesheetAction::make()
                    ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class)
                    ->label('View'),
                ExportTimesheetAction::make()
                    ->color('gray')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class),
                // Tables\Actions\BulkActionGroup::make([
                //     ExportTransmittalAction::make()
                //         ->label('Transmittal'),
                // ])
                //     ->label('Export')
                //     ->icon('heroicon-o-document-arrow-down'),
                // Tables\Actions\BulkActionGroup::make([
                //     ExportTimesheetAction::make()
                //         ->label('Timesheet'),
                //     ExportTransmittalAction::make()
                //         ->label('Transmittal'),
                // ])
                //     ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class)
                //     ->label('Export')
                //     ->icon('heroicon-o-document-arrow-down'),
                Tables\Actions\BulkAction::make('generate')
                    ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class)
                    ->icon('heroicon-o-bolt')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalIconColor('danger')
                    ->modalDescription(app(GenerateTimesheetAction::class, ['name' => null])->generateConfirmation())
                    ->form(app(GenerateTimesheetAction::class, ['name' => null])->generateForm())
                    ->action(fn (Collection $records, array $data) => app(GenerateTimesheetAction::class, ['name' => null])->generateAction($records, $data)),
                DeleteTimesheetAction::make('delete')
                    ->visible(fn () => ($this->filters['model'] ?? Employee::class) === Employee::class),
            ])
            ->deferLoading()
            ->deselectAllRecordsWhenFiltered(false)
            ->recordAction(null)
            ->defaultSort(fn () => ($this->filters['model'] ?? Employee::class) == Employee::class ? 'full_name' : null, 'asc');
    }

    public function thaumaturge(string $id)
    {
        $timelog = $this->timelogs->first(fn ($timelog) => $timelog->id === $id);

        if ($timelog->pseudo === false && $this->action === 'delete') {
            return;
        }

        $employee = $timelog->employee;

        $date = $timelog->time->startOfDay();

        DB::transaction(function () use ($employee, $timelog, $date, $id) {
            match ($this->action) {
                'delete' => $timelog->delete(),
                'hide' => $timelog?->forceFill(['shadow' => ! $timelog->shadow])->save(),
            };

            if ($this->action === 'delete') {
                $this->timelogs->forget($this->timelogs->search(fn ($timelog) => $timelog->id === $id));
            }

            $schedules = $employee->schedules()
                ->active($date, $date)
                ->get()
                ->merge(Schedule::where('global', true)->active($date, $date)->get());

            $shift = $schedules->first(fn (Schedule $schedule) => $schedule->isActive($date));

            ProcessAffectedTimetables::dispatch([
                [
                    'employee_id' => $employee->id,
                    'date' => $date->format('Y-m-d'),
                    'shift_id' => $shift?->id,
                ],
            ]);
        });
    }
}
