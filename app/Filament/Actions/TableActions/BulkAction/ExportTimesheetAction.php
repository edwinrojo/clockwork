<?php

namespace App\Filament\Actions\TableActions\BulkAction;

use App\Jobs\ExportTimesheets;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\TimesheetExporter;
use Exception;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ExportTimesheetAction extends BulkAction
{
    public static function make(?string $name = null): static
    {
        $class = static::class;

        $name ??= 'export-timesheet';

        $static = app($class, ['name' => $name]);

        $static->configure();

        return $static;
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->requiresConfirmation();

        $this->icon('heroicon-o-clipboard-document-list');

        $this->modalHeading('Export Timesheets');

        $this->modalDescription($this->exportConfirmation());

        $this->modalIcon('heroicon-o-document-arrow-down');

        $this->modalWidth('lg');

        $this->slideOver();

        $this->closeModalByClickingAway(false);

        $this->form($this->exportForm());

        $this->action(fn (Collection $records, array $data) => $this->exportAction($records, $data));
    }

    public function exportConfirmation(): Htmlable
    {
        $html = <<<'HTML'
            <!-- <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--warning-400);--c-600:var(--warning-600);">
                Note: Exporting in CSC format does not include employees with no timesheet for the selected period.
                You may need to generate their timesheets manually otherwise.
            </span> -->
        HTML;

        return str($html)->toHtmlString();
    }

    public function exportAction(Collection|Employee $employee, array $data): StreamedResponse|BinaryFileResponse|Notification
    {
        $actionException = new class extends Exception
        {
            public function __construct(public readonly ?string $title = null, public readonly ?string $body = null)
            {
                parent::__construct();
            }
        };

        try {
            $max = 300 - ($data['individual'] ? 100 : 0) - ($data['transmittal'] > 0 ? 25 : 0);

            if ($employee instanceof Collection && $employee->count() > $max) {
                throw new $actionException('Too many records', "To prevent server overload, please select no more than $max records.");
            }

            $exporter = (new TimesheetExporter)
                ->employee($employee)
                ->month($data['month'])
                ->period($data['period'])
                ->dates($data['dates'] ?? [])
                ->format($data['format'])
                ->size($data['size'])
                ->user(@$data['user'] ? User::find(@$data['user']) : user())
                ->individual($data['individual'] ?? false)
                ->transmittal($data['transmittal'] ?? 0)
                ->grouping($data['grouping'] ?? false)
                ->single($data['single'] ?? false)
                ->signature([
                    'electronic' => @$data['digital_signature'] ?: @$data['electronic_signature'],
                    'digital' => @$data['digital_signature'],
                ])
                ->misc([
                    'calculate' => @$data['calculate'],
                    'supervisor' => @$data['supervisor'],
                    'officer' => @$data['officer'],
                    'weekends' => @$data['weekends'],
                    'holidays' => @$data['holidays'],
                    'highlights' => @$data['highlights'],
                    'absences' => @$data['absences'],
                ]);

            if ($employee instanceof Collection && $employee->count() > 10) {
                ExportTimesheets::dispatch($exporter);

                return Notification::make()
                    ->info()
                    ->title('Generating export')
                    ->body('Please wait while we generate the timesheets. You will be notified once it is ready.')
                    ->send();
            } else {
                return $exporter->download();
            }
        } catch (ProcessFailedException $exception) {
            $message = $employee instanceof Collection ? 'Failed to export timesheets' : "Failed to export {$employee->name}'s timesheet";

            return Notification::make()
                ->danger()
                ->title($message)
                ->body($exception->getMessage())
                ->send();
        } catch (Exception $exception) {
            if ($exception instanceof $actionException) {
                return Notification::make()
                    ->danger()
                    ->title($exception->title)
                    ->body($exception->body)
                    ->send();
            }

            throw $exception;
        }
    }

    public function exportForm(bool $preview = false, bool $employee = false): array
    {
        $config = [
            Select::make('size')
                ->visible($preview)
                ->placeholder('Paper Size')
                ->default(fn ($livewire) => $livewire->filters['folio'] ?? 'folio')
                ->required()
                ->options([
                    'a4' => 'A4 (210mm x 297mm)',
                    'letter' => 'Letter (216mm x 279mm)',
                    'folio' => 'Folio (216mm x 330mm)',
                    'legal' => 'Legal (216mm x 356mm)',
                ]),
            // Select::make('transmittal')
            //     ->visible($bulk)
            //     ->live()
            //     ->default(false)
            //     ->boolean()
            //     ->required()
            //     ->placeholder('Generate transmittal'),
            Select::make('user')
                ->visible(fn () => ($user = user())->developer && $user->superuser)
                ->options(User::take(25)->whereNot('id', Auth::id())->orderBy('name')->pluck('name', 'id'))
                ->getSearchResultsUsing(fn ($search) => User::take(25)->whereNot('id', Auth::id())->where('name', 'ilike', "%{$search}%")->pluck('name', 'id'))
                ->searchable(),
            // Checkbox::make('electronic_signature')
            //     ->helperText('Electronically sign the document.')
            //     ->default(fn ($livewire) => $livewire->filters['electronic_signature'] ?? false)
            //     ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
            //         if (! $value) {
            //             return;
            //         }

            //         $user = $get('user') ? User::find($get('user')) : user();

            //         $name = $get('user')
            //             ? str("$user->name'")->when(! str($user->name)->endsWith('s'), fn ($str) => $str->append('s'))->toString()
            //             : 'your';

            //         if ($user->signature?->specimen === null) {
            //             return $fail('Please configure '.($get('user') ? $name : 'your').' electronic signature certificate first');
            //         }

            //         if ($user->signature?->certificate === null) {
            //             return $fail('Please configure '.($get('user') ? $name : 'your').' digital signature certificate first');
            //         }
            //     }),
            // Checkbox::make('digital_signature')
            //     ->helperText('Digitally sign the document to prevent tampering.')
            //     ->dehydrated(true)
            //     ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
            //         if (! $value) {
            //             return;
            //         }

            //         $user = $get('user') ? User::find($get('user')) : user();

            //         $name = $get('user')
            //             ? str("$user->name'")->when(! str($user->name)->endsWith('s'), fn ($str) => $str->append('s'))->toString()
            //             : 'your';

            //         if ($user->signature?->specimen === null) {
            //             return $fail('Please configure '.($get('user') ? $name : 'your').' electronic signature certificate first');
            //         }

            //         if ($user->signature?->certificate === null) {
            //             return $fail('Please configure '.($get('user') ? $name : 'your').' digital signature certificate first');
            //         }
            //     }),
        ];

        $period = [
            Radio::make('format')
                ->default(fn ($livewire) => $livewire->filters['format'] ?? (in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['employee', 'director', 'manager']) ? 'csc' : 'default'))
                ->required()
                ->options([
                    'default' => 'Default format',
                    'csc' => 'CSC format',
                    'preformatted' => 'CSC format (preformatted)',
                ])
                ->descriptions([
                    'default' => 'Raw attendance data. Displays more detailed information about the employees\' attendance.',
                    'csc' => ($preview ? 'Preview' : 'Export').' in CSC form with attendance data based off the employees\' set schedules. You may need to generate their timesheets manually if they have no timesheet data for the selected period. Ignores employees with no timesheet data.',
                    'preformatted' => 'Schedule-agnostic attendance preformatted in CSC form. Attendance data are fetched as is and may not reflect the actual schedule of the employees. Any digital signature aside from officer will not be applied.',
                ]),
            TextInput::make('month')
                ->hidden($employee)
                ->default(fn ($livewire) => $livewire->filters['month'] ?? (today()->day > 15 ? today()->startOfMonth()->format('Y-m') : today()->subMonth()->format('Y-m')))
                ->type('month')
                ->required(),
            Select::make('period')
                ->default(function (Employee|Timesheet|null $record, $livewire) {
                    if (in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['director', 'manager'])) {
                        return $record->certified['full'] ? 'full' : ($record->certified['1st'] ? '1st' : '2nd');
                    }

                    return $livewire->filters['period'] ?? (today()->day > 15 ? '1st' : 'full');
                })
                ->required()
                ->reactive()
                ->options(function () {
                    if (in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['director', 'manager'])) {
                        return [
                            'full' => 'Full month',
                            '1st' => 'First half',
                            '2nd' => 'Second half',
                        ];
                    }

                    return [
                        'full' => 'Full month',
                        '1st' => 'First half',
                        '2nd' => 'Second half',
                        'regular' => 'Regular days',
                        'overtime' => 'Overtime work',
                        'dates' => 'Custom dates',
                        'range' => 'Custom range',
                    ];
                })
                ->disableOptionWhen(function (Employee|Timesheet|null $record, Get $get, ?string $value) {
                    if (in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['director', 'manager'])) {
                        return match ($value) {
                            'full' => ! $record->certified['full'],
                            '1st' => ! $record->certified['1st'],
                            '2nd' => ! $record->certified['2nd'],
                        };
                    }

                    if ($get('format') === 'csc') {
                        return false;
                    }

                    if ($get('format') === 'preformatted') {
                        return ! in_array($value, ['full', '1st', '2nd', 'dates', 'range']);
                    }

                    return match ($value) {
                        'full', '1st', '2nd', 'dates', 'range' => false,
                        default => true,
                    };
                })
                ->dehydrateStateUsing(function (Get $get, ?string $state) {
                    if ($state !== 'range') {
                        return $state;
                    }

                    return $state.'|'.date('d', strtotime($get('from'))).'-'.date('d', strtotime($get('to')));
                })
                ->in(fn (Select $component): array => array_keys($component->getEnabledOptions())),
            Group::make()
                ->columns(2)
                ->visible(fn (Get $get) => $get('period') === 'range')
                ->columnSpanFull()
                ->schema([
                    DatePicker::make('from')
                        ->label('Start')
                        ->default(fn ($livewire) => $livewire->filters['from'] ?? (today()->day > 15 ? today()->startOfMonth()->format('Y-m-d') : today()->subMonth()->startOfMonth()->format('Y-m-d')))
                        ->validationAttribute('start')
                        ->minDate(fn (Get $get) => $get('month').'-01')
                        ->maxDate(fn (Get $get) => Carbon::parse($get('month'))->endOfMonth())
                        ->markAsRequired()
                        ->rule('required')
                        ->dehydrated(false)
                        ->beforeOrEqual('to'),
                    DatePicker::make('to')
                        ->label('End')
                        ->default(fn ($livewire) => $livewire->filters['to'] ?? (today()->day > 15 ? today()->endOfMonth()->format('Y-m-d') : today()->subMonth()->setDay(15)->format('Y-m-d')))
                        ->validationAttribute('end')
                        ->minDate(fn (Get $get) => $get('month').'-01')
                        ->maxDate(fn (Get $get) => Carbon::parse($get('month'))->endOfMonth())
                        ->markAsRequired()
                        ->rule('required')
                        ->dehydrated(false)
                        ->afterOrEqual('from'),
                ]),
            Repeater::make('dates')
                ->visible(fn (Get $get) => $get('period') === 'dates')
                ->default(fn ($livewire) => $livewire->filters['dates'] ?? [])
                ->required()
                ->reorderable(false)
                ->addActionLabel('Add a date')
                ->grid(2)
                ->columnSpanFull()
                ->simple(
                    DatePicker::make('date')
                        ->minDate(fn (Get $get) => $get('../../month').'-01')
                        ->maxDate(fn (Get $get) => Carbon::parse($get('../../month'))->endOfMonth())
                        ->markAsRequired()
                        ->rule('required')
                ),
        ];
        $export = [
            Select::make('size')
                ->visible(! $preview)
                ->placeholder('Paper Size')
                ->default(fn ($livewire) => $livewire->filters['folio'] ?? 'folio')
                ->required()
                ->options([
                    'a4' => 'A4',
                    'letter' => 'Letter',
                    'folio' => 'Folio',
                    'legal' => 'Legal',
                ]),
            Select::make('transmittal')
                ->live()
                ->visible(! $preview)
                ->default(fn ($livewire) => $livewire->filters['transmittal'] ?? 0)
                ->options([0, 1, 2, 3, 5])
                ->in([0, 1, 2, 3, 5])
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Input the number of copies of transmittal to be generated.'),
            Select::make('grouping')
                ->visible(! $preview)
                ->disabled(fn (Get $get) => $get('transmittal') <= 0)
                ->default(fn ($livewire) => $livewire->filters['grouping'] ?? 'offices')
                ->options([
                    'offices' => 'Office',
                    false => 'None',
                ])
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('
                    Grouping by office might generate multiple timesheets for employees with multiple offices.
                    No grouping will generate a single transmittal for all selected employees.
                '),
        ];

        return $preview
            ? [...$period, ...$export]
            : [
                Tabs::make()->contained(false)->tabs([
                    Tab::make('Timesheet')
                        ->schema($period),
                    Tab::make('Options')
                        ->schema([...$export, ...$config]),
                    Tab::make('Miscellaneous')
                        ->schema([
                            Toggle::make('individual')
                                ->default(false)
                                ->helperText('Export employee timesheet separately generating multiple files to be downloaded as an archive.'),
                            Toggle::make('calculate')
                                ->default(false)
                                ->helperText('Calculate days worked and minutes of undertime (CSC Form).'),
                            Toggle::make('single')
                                ->default(false)
                                ->helperText('Force single timesheet per page.'),
                            Toggle::make('supervisor')
                                ->default(true)
                                ->helperText('Include supervisor field in the timesheet.'),
                            Toggle::make('officer')
                                ->default(true)
                                ->helperText('Include officer-in-charge field in the timesheet.'),
                            Toggle::make('weekends')
                                ->default(true)
                                ->helperText('Label weekends in the timesheet if no attendance data is present.'),
                            Toggle::make('holidays')
                                ->default(true)
                                ->helperText('Label holidays in the timesheet if no attendance data is present.'),
                            Toggle::make('highlights')
                                ->default(true)
                                ->reactive()
                                ->afterStateUpdated(fn ($get, $set, $state) => $set('absences', $state ? $get('absences') : false))
                                ->helperText('Highlight blank or missing entries in the timesheet.'),
                            Toggle::make('absences')
                                ->default(true)
                                ->disabled(fn (Get $get) => ! $get('highlights'))
                                ->helperText('Highlight absent entries red in the timesheet (needs highlights enabled).'),
                        ]),
                ]),
            ];
    }
}
