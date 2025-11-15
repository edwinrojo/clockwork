<?php

namespace App\Filament\Employee\Resources\TimesheetResource\Pages;

use App\Enums\AnnotationField;
use App\Enums\PaperSize;
use App\Enums\TimelogState;
use App\Enums\TimesheetPeriod;
use App\Filament\Employee\Resources\TimesheetResource;
use App\Jobs\CertifyTimesheets;
use App\Jobs\ProcessAffectedTimetables;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Timelog;
use App\Models\Timesheet;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ViewTimesheet extends ViewRecord
{
    use HasFiltersForm;

    protected static string $resource = TimesheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->navigate('prev'),
            $this->navigate('next'),
            $this->period(),
            $this->annotate(),
            ActionGroup::make([
                $this->rectify(),
                $this->certify(),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $infolist
            ->schema([
                Group::make()
                    ->columnSpanFull()
                    ->columns(12)
                    ->schema([
                        TextEntry::make('timesheet')
                            ->columnSpan(5)
                            ->formatStateUsing(function (): View {
                                $period = isset($this->filters['period'])
                                    ? ($this->filters['period'] instanceof TimesheetPeriod ? $this->filters['period']->value : $this->filters['period'])
                                    : 'full';

                                return view('filament.validation.pages.csc', [
                                    'timesheets' => [$this->record->setSpan($period)],
                                    'styles' => false,
                                    'month' => false,
                                ]);
                            }),
                        TextEntry::make('timelogs')
                            ->columnSpan(7)
                            ->formatStateUsing(function (): View {
                                $period = isset($this->filters['period'])
                                    ? ($this->filters['period'] instanceof TimesheetPeriod ? $this->filters['period']->value : $this->filters['period'])
                                    : 'full';

                                $month = Carbon::parse($this->record->month);

                                $from = $period === '2nd' ? 16 : 1;

                                $to = $period === '1st' ? 15 : $month->daysInMonth();

                                return view('filament.validation.pages.default', [
                                    'employees' => [$this->record->employee->load(['scanners', 'timelogs'])],
                                    'month' => $month,
                                    'period' => $this->filters['period'] ?? 'full',
                                    'from' => $from,
                                    'to' => $to,
                                    'preview' => true,
                                ]);
                            }),
                    ]),
                TextEntry::make('scanners')
                    ->columnSpanFull()
                    ->state(function () {
                        $scanners = $this->record->employee->scanners()
                            ->orderBy('priority', 'desc')
                            ->orderBy('name')
                            ->get()
                            ->map(function ($scanner) {
                                return <<<HTML
                                    <span
                                        class="px-2 py-1 mr-2 font-xs text-nowrap"
                                        style="border-radius:0.3em;background-color:{$scanner->background_color};text-color:{$scanner->foreground_color};"
                                    >
                                        {$scanner->name} ({$scanner->pivot->uid})
                                    </span>
                                HTML;
                            });

                        return str($scanners->join(''))
                            ->wrap('<span>', '</span>')
                            ->toHtmlString();
                    }),
                Group::make([
                    TextEntry::make('days')
                        ->label('Days'),
                    TextEntry::make('overtime')
                        ->label('Overtime')
                        ->state(function (Timesheet $record) {
                            return $record->getOvertime(true);
                        }),
                    TextEntry::make('undertime')
                        ->label('Undertime')
                        ->state(function (Timesheet $record) {
                            return $record->getUndertime(true);
                        }),
                    TextEntry::make('missed')
                        ->label('Missed')
                        ->state(function (Timesheet $record) {
                            return $record->getMissed(true);
                        }),
                ]),
            ]);
    }

    protected function navigate(string $to = 'next'): Action
    {
        $timesheet = $this->record->employee->timesheets()
            ->whereColumn('timesheets.id', 'timesheets.timesheet_id')
            ->where('timesheets.month', $to === 'next' ? '>' : '<', "{$this->record->month}-01")
            ->orderBy('month', $to === 'next' ? 'asc' : 'desc')
            ->limit(1)
            ->first();

        $month = $timesheet ? Carbon::parse($timesheet->month) : null;

        $url = $timesheet
            ? route('filament.employee.resources.timesheets.view', ['record' => $timesheet?->id, 'filters' => ['period' => $this->filters['period'] ?? null]])
            : null;

        return Action::make('navigate-to'.ucfirst($month?->format('F-Y') ?? ''))
            ->icon($to === 'next' ? 'heroicon-o-forward' : 'heroicon-o-backward')
            ->iconButton()
            ->disabled($timesheet === null)
            ->url($url);
    }

    protected function period(): Action
    {
        return FilterAction::make()
            ->color('primary')
            ->iconButton()
            ->schema([
                Select::make('period')
                    ->options(TimesheetPeriod::class)
                    ->default(TimesheetPeriod::FULL),
            ]);
    }

    protected function rectify(): Action
    {
        return Action::make('rectify')
            ->icon('gmdi-rebase-edit')
            ->modalIcon('gmdi-rebase-edit')
            ->modalDescription('This will allow you to correct your timesheet by adjusting erroneous punch states.')
            ->modalSubmitActionLabel('Save')
            ->modalWidth('lg')
            ->slideOver()
            ->successNotificationTitle('Timesheet successfully rectified.')
            ->failureNotificationTitle('Something went wrong while rectifying your timesheet.')
            ->hidden(function (Timesheet $record) {
                return $record->export()->exists()
                    ?: $record->timesheets()->where('span', '1st')->exists() && $record->timesheets()->where('span', '2nd')->exists();
            })
            ->schema([
                DatePicker::make('date')
                    ->reactive()
                    ->dehydrated(false)
                    ->markAsRequired()
                    ->debounce(500)
                    ->minDate(Carbon::parse($this->record->month)->startOfMonth())
                    ->maxDate(Carbon::parse($this->record->month)->endOfMonth())
                    ->rule('required')
                    ->rule(fn () => function ($attribute, $value, $fail) {
                        if ($value === null) {
                            return;
                        }

                        $employee = Employee::find(Filament::auth()->id());

                        if ($employee->timelogs()->whereDate('time', $value)->doesntExist()) {
                            $fail('No data found for the selected date.');
                        }
                    })
                    ->afterStateUpdated(function ($component, $livewire, $set, $state) {
                        $livewire->validateOnly($component->getStatePath());

                        $timelogs = Employee::find(Filament::auth()->id())
                            ->timelogs()
                            ->with('scanner')
                            ->whereDate('time', $state)
                            ->reorder()
                            ->orderBy('time')
                            ->get();

                        $set('timelogs', $timelogs->map(function ($timelog) {
                            return [
                                'id' => $timelog->id,
                                'scanner' => $timelog->scanner->name,
                                'time' => Carbon::parse($timelog->time)->format('H:i'),
                                'state' => $timelog->state,
                                'recast' => $timelog->recast,
                            ];
                        })->toArray());
                    }),
                Repeater::make('timelogs')
                    ->visible(fn (Get $get) => Employee::find(Filament::auth()->id())->timelogs()->whereDate('time', $get('date'))->exists())
                    ->label('Records')
                    ->defaultItems(0)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->itemLabel(function (array $state) {
                        $current = Timelog::find($state['id']);

                        $scanner = $state['scanner'];

                        if ($state['recast']) {
                            $rectified = <<<HTML
                            <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--warning-400);--c-600:var(--warning-600);">
                                {$scanner} ({$current->original->state->getLabel()})
                            </span>
                        HTML;

                            return str($rectified)->append($current->state !== $state['state'] ? '*' : '')->toHtmlString();
                        }

                        return $current->state === $state['state'] ? $scanner : "$scanner*";
                    })
                    ->columns(5)
                    ->required()
                    ->schema([
                        TextInput::make('time')
                            ->extraInputAttributes(['readonly' => true])
                            ->dehydrated(false),
                        Select::make('state')
                            ->reactive()
                            ->options(TimelogState::class)
                            ->columnSpan(4)
                            ->required()
                            ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                                if ($value === TimelogState::UNKNOWN) {
                                    $fail('Invalid state.');
                                }

                                if (in_array($value, [TimelogState::CHECK_IN_PM, TimelogState::CHECK_OUT_PM]) && $get('time') < '12:00') {
                                    $fail('Invalid state.');
                                }
                            }),
                    ]),
            ])
            ->action(function (Action $action, array $data) {
                try {
                    DB::transaction(function () use ($data) {
                        $timelogs = collect($data['timelogs'])->mapWithKeys(fn ($timelog) => [$timelog['id'] => @$timelog['state']]);

                        $existing = Timelog::find(collect($data['timelogs'])->pluck('id'));

                        $employee = Filament::auth()->user();

                        $timetables = [];

                        $existing->filter(fn ($timelog) => $timelog->state !== $timelogs[$timelog->id])->each(function ($timelog) use ($timelogs, $employee, &$timetables) {
                            if ($timelog->recast) {
                                if ($timelog->original->state !== $timelogs[$timelog->id]) {
                                    $timelog->forceFill(['state' => $timelogs[$timelog->id]])->save();
                                } else {
                                    $timelog->original->forceFill(['masked' => false])->save();

                                    $timelog->delete();
                                }
                            } else {
                                $data = [
                                    'time' => $timelog->time,
                                    'state' => $timelogs[$timelog->id],
                                    'mode' => $timelog->mode,
                                    'uid' => $timelog->uid,
                                    'device' => $timelog->device,
                                    'recast' => true,
                                ];

                                $timelog->forceFill(['masked' => true])->save();

                                $timelog->revision()->make()->forceFill($data)->save();
                            }

                            $date = $timelog->time->clone()->startOfDay();

                            $schedules = $employee->schedules()
                                ->active($date, $date)
                                ->get()
                                ->merge(Schedule::where('global', true)->active($date, $date)->get());

                            $shift = $schedules->first(fn (Schedule $schedule) => $schedule->isActive($date));

                            $timetables[] = [
                                'employee_id' => $employee->id,
                                'date' => $date->format('Y-m-d'),
                                'shift_id' => $shift?->id,
                            ];
                        });

                        if (! empty($timetables)) {
                            ProcessAffectedTimetables::dispatch($timetables);
                        }
                    });

                    $action->sendSuccessNotification();
                } catch (Exception) {
                    $action->sendFailureNotification();
                }
            });
    }

    protected function certify(): Action
    {
        return Action::make('certify')
            ->icon('gmdi-fact-check-o')
            ->modalIcon('gmdi-fact-check-o')
            ->modalWidth('xl')
            ->modalDescription(function () {
                $html = <<<'HTML'
                    Certify your timesheet for the selected period of this month for your superiors to validate and verify.

                    <span class="inline-block mt-4 text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--warning-400);--c-600:var(--warning-600);">
                        Please note that any further changes, if any, will not be reflected in the generated timesheet.
                    </span>
                HTML;

                return str($html)->toHtmlString();
            })
            ->modalSubmitActionLabel('Certify')
            ->closeModalByClickingAway(false)
            ->visible(fn () => Auth::user()->signature?->certificate)
            ->successNotificationTitle('Timesheet certification process initiated.')
            ->hidden(function (Timesheet $record) {
                /** @var Employee */
                $user = Auth::user();

                return ! $user->signature()->exists() ?:
                    $record->export()->exists() &&
                    $record->timesheets()->where('span', '1st')->exists() &&
                    $record->timesheets()->where('span', '2nd')->exists();
            })
            ->action(function (Action $action, array $data) {
                CertifyTimesheets::dispatch([$this->record->id], 'employee', Auth::id(), [
                    'period' => $data['period'],
                    'accomplishment' => base64_encode($data['accomplishment']->get()),
                ]);

                $action->sendSuccessNotification();
            })
            ->schema([
                Tabs::make()
                    ->contained(false)
                    ->schema([
                        Tab::make('Acknowledgement')
                            ->schema([
                                Select::make('period')
                                    ->options(TimesheetPeriod::class)
                                    ->default($this->filters['period'] ?? TimesheetPeriod::FULL)
                                    ->required()
                                    ->helperText('Select the period of this month to certify.')
                                    ->rule(fn () => function ($attribute, $value, $fail) {
                                        if ($value === null) {
                                            return;
                                        }

                                        if ($value === TimesheetPeriod::FULL) {
                                            if ($this->record->export()->exists()) {
                                                return $fail('Timesheet is already generated and certified.');
                                            }

                                            return;
                                        }

                                        if ($this->record->timesheets()->where('span', $value)->exists()) {
                                            return $fail('Timesheet is already generated and certified.');
                                        }
                                    }),
                                FileUpload::make('accomplishment')
                                    ->hintIcon('heroicon-o-question-mark-circle')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->maxSize(12 * 1024)
                                    ->required()
                                    ->helperText('Upload your accomplishment report for the selected period.')
                                    ->storeFiles(false)
                                    ->hintIconTooltip(<<<'HTML'
                                        Accomplishment will be signed and verified by your superiors along with your timesheet.
                                        Please note that this will only apply an invisible signature field.
                                        If you wish, you may both your add your superiors' electronic signature to the document before uploading.
                                        Once uploaded, the document will be locked and cannot be modified.
                                    HTML),
                                Checkbox::make('confirmation')
                                    ->markAsRequired()
                                    ->accepted()
                                    ->extraAttributes(['class' => 'self-start mt-1'])
                                    ->validationMessages(['accepted' => 'You must certify first.'])
                                    ->dehydrated(false)
                                    ->rule(fn () => function ($attribute, $value, $fail) {
                                        /** @var Employee */
                                        $user = Auth::user();

                                        if ($user->signature === null || $user->signature->certificate === null || $user->signature->password === null) {
                                            return $fail('You must have to configure your digital signature first.');
                                        }
                                    })
                                    ->label(function () {
                                        return <<<'LABEL'
                                            I certify that the information provided is accurate and correctly reports
                                            the hours of work performed in accordance with the prescribed office hours
                                        LABEL;
                                    }),
                            ]),
                        Tab::make('Miscellaneous')
                            ->schema([
                                Select::make('size')
                                    ->disabled()
                                    ->required()
                                    ->default('folio')
                                    ->options(PaperSize::class),
                            ]),
                    ]),
            ]);
    }

    protected function annotate(): Action
    {
        return Action::make('annotate')
            ->icon('gmdi-edit-calendar-o')
            ->modalIcon('gmdi-edit-calendar-o')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Save')
            ->modalDescription(function () {
                $html = <<<'HTML'
                    Annotate your timesheet to provide additional information or context for your superiors to review and verify.

                    <span class="inline-block mt-4 text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--warning-400);--c-600:var(--warning-600);">
                        Annotations will not be reflected if there is a record for the selected date or designated arrival and departure times.
                    </span>
                HTML;

                return str($html)->toHtmlString();
            })
            ->slideOver()
            ->model($this->record)
            ->successNotificationTitle('Annotations successfully saved.')
            ->fillForm(fn (Timesheet $record) => ['details' => [
                ...$record->details,
            ]])
            ->schema([
                Builder::make('details.annotations')
                    ->addActionLabel('Add annotation')
                    ->reorderable()
                    ->blocks([
                        Block::make('entire_date')
                            ->columns(12)
                            ->schema([
                                DatePicker::make('date')
                                    ->rule('required')
                                    ->markAsRequired()
                                    ->minDate(Carbon::parse($this->record->month)->startOfMonth())
                                    ->maxDate(Carbon::parse($this->record->month)->endOfMonth())
                                    ->columnSpan(12),
                                TextInput::make('note')
                                    ->label('Note')
                                    ->rule('required')
                                    ->markAsRequired()
                                    ->maxLength(60)
                                    ->columnSpan(12)
                                    ->hint('Excessive texts will be truncated.'),
                            ]),
                        Block::make('specified_field')
                            ->columns(12)
                            ->schema([
                                DatePicker::make('date')
                                    ->rule('required')
                                    ->markAsRequired()
                                    ->minDate(Carbon::parse($this->record->month)->startOfMonth())
                                    ->maxDate(Carbon::parse($this->record->month)->endOfMonth())
                                    ->columnSpan(6),
                                Select::make('field')
                                    ->options([
                                        AnnotationField::ARRIVAL_1->value => AnnotationField::ARRIVAL_1->getLabel(),
                                        AnnotationField::DEPARTURE_1->value => AnnotationField::DEPARTURE_1->getLabel(),
                                        AnnotationField::ARRIVAL_2->value => AnnotationField::ARRIVAL_2->getLabel(),
                                        AnnotationField::DEPARTURE_2->value => AnnotationField::DEPARTURE_2->getLabel(),
                                    ])
                                    ->required()
                                    ->columnSpan(6),
                                TextInput::make('note')
                                    ->label('Note')
                                    ->rule('required')
                                    ->markAsRequired()
                                    ->maxLength(60)
                                    ->columnSpan(12)
                                    ->hint('Excessive texts will be truncated.'),
                            ]),
                        Block::make('date_range')
                            ->columns(12)
                            ->schema([
                                DatePicker::make('date')
                                    ->rule('required')
                                    ->markAsRequired()
                                    ->minDate(Carbon::parse($this->record->month)->startOfMonth())
                                    ->maxDate(Carbon::parse($this->record->month)->endOfMonth())
                                    ->columnSpan(6),
                                DatePicker::make('to')
                                    ->rule('required')
                                    ->markAsRequired()
                                    ->minDate(Carbon::parse($this->record->month)->startOfMonth())
                                    ->maxDate(Carbon::parse($this->record->month)->endOfMonth())
                                    ->after('date')
                                    ->columnSpan(6),
                                TextInput::make('note')
                                    ->label('Note')
                                    ->rule('required')
                                    ->markAsRequired()
                                    ->maxLength(60)
                                    ->columnSpan(12)
                                    ->hint('Excessive texts will be truncated.'),
                            ]),
                    ]),
            ])
            ->action(function (Action $action, Timesheet $record, array $data) {
                $record->update($data);
            });
    }
}
