<?php

namespace App\Filament\Actions\TableActions\BulkAction;

use App\Actions\ExportTransmittal;
use App\Models\Employee;
use App\Models\Group;
use App\Models\User;
use Exception;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ExportTransmittalAction extends BulkAction
{
    public static function make(?string $name = null): static
    {
        $class = static::class;

        $name ??= 'export-transmittal';

        $static = app($class, ['name' => $name]);

        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresConfirmation();

        $this->icon('heroicon-o-clipboard-document-check');

        $this->modalHeading('Export Transmittal');

        $this->modalDescription($this->exportConfirmation());

        $this->modalIcon('heroicon-o-document-arrow-down');

        $this->form($this->exportForm());

        $this->action(fn (Collection $records, array $data) => $this->exportAction($records, $data));
    }

    protected function exportConfirmation(): Htmlable
    {
        $html = <<<'HTML'
            <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--warning-400);--c-600:var(--warning-600);">
                Note: Kindly use the same options as how you'd like the timesheet exported for consistency.
            </span>
        HTML;

        return str($html)->toHtmlString();
    }

    protected function exportAction(Collection|Employee $employee, array $data): StreamedResponse|BinaryFileResponse|Notification
    {
        $actionException = new class extends Exception
        {
            public function __construct(public readonly ?string $title = null, public readonly ?string $body = null)
            {
                parent::__construct();
            }
        };

        try {
            if ($employee instanceof Collection && $employee->count() > 100) {
                throw new $actionException('Too many records', 'To prevent server overload, please select less than 100 records');
            }

            return (new ExportTransmittal)
                ->employee($employee)
                ->month($data['month'])
                ->period($data['period'])
                ->dates($data['dates'] ?? [])
                ->format($data['format'])
                ->size($data['size'])
                ->strict($data['strict'] ?? false)
                ->current($data['current'] ?? false)
                ->user(@$data['user'] ? User::find($data['user']) : user())
                // ->signature($data['electronic_signature'])
                // ->password($data['digital_signature'] ? $data['password'] : null)
                ->groups($data['groups'] ?? [])
                ->download();
        } catch (ProcessFailedException $exception) {
            $message = $employee instanceof Collection ? 'Failed to export timesheets' : "Failed to export {$employee->name}'s timesheet";

            return Notification::make()
                ->danger()
                ->title($message)
                ->body('Please try again later')
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

    protected function exportForm(): array
    {
        return [
            // Checkbox::make('individual')
            //     ->hintIcon('heroicon-o-question-mark-circle')
            //     ->hintIconTooltip('Export employee timesheet separately generating multiple files to be downloaded as an archive. However, this requires more processing time and to prevent server overload or request timeouts, please select no more than 25 records.')
            //     ->rule(fn (HasTable $livewire) => function ($attribute, $value, $fail) use ($livewire) {
            //         if ($value && count($livewire->selectedTableRecords) > 25) {
            //             $fail('Please select less than 25 records when exporting individually.');
            //         }
            //     }),
            TextInput::make('month')
                ->live()
                ->default(fn ($livewire) => $livewire->filters['month'] ?? (today()->day > 15 ? today()->startOfMonth()->format('Y-m') : today()->subMonth()->format('Y-m')))
                ->type('month')
                ->required(),
            Select::make('period')
                ->default(fn ($livewire) => $livewire->filters['period'] ?? (today()->day > 15 ? '1st' : 'full'))
                ->required()
                ->live()
                ->options([
                    'full' => 'Full month',
                    '1st' => 'First half',
                    '2nd' => 'Second half',
                    'regular' => 'Regular days',
                    'overtime' => 'Overtime work',
                    'dates' => 'Custom dates',
                    'range' => 'Custom range',
                ])
                ->disableOptionWhen(function (Get $get, ?string $value) {
                    if ($get('format') === 'csc') {
                        return false;
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
            DatePicker::make('from')
                ->label('Start')
                ->visible(fn (Get $get) => $get('period') === 'range')
                ->default(fn ($livewire) => $livewire->filters['from'] ?? (today()->day > 15 ? today()->startOfMonth()->format('Y-m-d') : today()->subMonth()->startOfMonth()->format('Y-m-d')))
                ->validationAttribute('start')
                ->minDate(fn (Get $get) => $get('month').'-01')
                ->maxDate(fn (Get $get) => Carbon::parse($get('month'))->endOfMonth())
                ->required()
                ->dehydrated(false)
                ->beforeOrEqual('to'),
            DatePicker::make('to')
                ->label('End')
                ->visible(fn (Get $get) => $get('period') === 'range')
                ->default(fn ($livewire) => $livewire->filters['to'] ?? (today()->day > 15 ? today()->endOfMonth()->format('Y-m-d') : today()->subMonth()->setDay(15)->format('Y-m-d')))
                ->validationAttribute('end')
                ->minDate(fn (Get $get) => $get('month').'-01')
                ->maxDate(fn (Get $get) => Carbon::parse($get('month'))->endOfMonth())
                ->required()
                ->dehydrated(false)
                ->afterOrEqual('from'),
            Repeater::make('dates')
                ->visible(fn (Get $get) => $get('period') === 'dates')
                ->default(fn ($livewire) => $livewire->filters['dates'] ?? [])
                ->required()
                ->reorderable(false)
                ->addActionLabel('Add a date')
                ->simple(
                    DatePicker::make('date')
                        ->minDate(fn (Get $get) => $get('../../month').'-01')
                        ->maxDate(fn (Get $get) => Carbon::parse($get('../../month'))->endOfMonth())
                        ->markAsRequired()
                        ->rule('required')
                ),
            Select::make('format')
                ->live()
                ->placeholder('Print format')
                ->default(fn ($livewire) => $livewire->filters['format'] ?? 'csc')
                ->required()
                ->options(['default' => 'Default format', 'csc' => 'CSC format'])
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Employees with no timesheet data for the selected period are not included in the timesheet export when using the CSC format.'),
            Select::make('size')
                ->live()
                ->placeholder('Paper Size')
                ->default(fn ($livewire) => $livewire->filters['folio'] ?? 'folio')
                ->required()
                ->options([
                    'a4' => 'A4 (210mm x 297mm)',
                    'letter' => 'Letter (216mm x 279mm)',
                    'folio' => 'Folio (216mm x 330mm)',
                    'legal' => 'Legal (216mm x 356mm)',
                ]),
            Select::make('groups')
                ->multiple()
                ->searchable()
                ->getSearchResultsUsing(fn (?string $search) => Group::where('name', 'ilike', "%$search%")->get()->pluck('name', 'name'))
                ->preload(),
            // Select::make('transmittal')
            //     ->visible($bulk)
            //     ->live()
            //     ->default(false)
            //     ->boolean()
            //     ->required()
            //     ->placeholder('Generate transmittal'),
            // Checkbox::make('electronic_signature')
            //     ->hintIcon('heroicon-o-check-badge')
            //     ->hintIconTooltip('Electronically sign the document. This does not provide security against tampering.')
            //     ->default(fn ($livewire) => $livewire->filters['electronic_signature'] ?? false)
            //     ->live()
            //     ->afterStateUpdated(fn ($get, $set, $state) => $set('digital_signature', $state ? $get('digital_signature') : false))
            //     ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
            //         $user = $get('user') ? User::find($get('user')) : user();

            //         if (! $user?->signature->verify($value)) {
            //             $fail('Configure your electronic signature first');
            //         }
            //     }),
            // Checkbox::make('digital_signature')
            //     ->hintIcon('heroicon-o-shield-check')
            //     ->hintIconTooltip('Digitally sign the document to prevent tampering.')
            //     ->dehydrated(true)
            //     ->live()
            //     ->afterStateUpdated(fn ($get, $set, $state) => $set('electronic_signature', $state ? true : $get('electronic_signature')))
            //     ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
            //         if ($value && ! $get('electronic_signature')) {
            //             $fail('Digital signature requires electronic signature');
            //         }
            //     }),
            TextInput::make('password')
                ->password()
                ->visible(fn (Get $get) => $get('digital_signature') && $get('electronic_signature'))
                ->markAsRequired(fn (Get $get) => $get('digital_signature'))
                ->rule(fn (Get $get) => $get('digital_signature') ? 'required' : '')
                ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                    $user = $get('user') ? User::find($get('user')) : user();

                    if (! $user?->signature->verify($value)) {
                        $fail('The password is incorrect');
                    }
                }),
        ];
    }
}
