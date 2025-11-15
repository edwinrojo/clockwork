<?php

namespace App\Filament\Secretary\Resources\TimesheetResource\Pages;

use App\Filament\Actions\ExportAttendanceAction;
use App\Filament\Secretary\Resources\TimesheetResource;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ListTimesheets extends ListRecords
{
    use HasFiltersForm;

    protected static string $resource = TimesheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAttendanceAction::make(),
            FilterAction::make()
                ->label('Options')
                ->icon('heroicon-o-adjustments-horizontal')
                ->modalHeading('Option')
                ->slideOver(false)
                ->modalDescription(function () {
                    $html = <<<'HTML'
                        <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--warning-400);--c-600:var(--warning-600);">
                            Note: You can still override these settings.
                        </span>
                    HTML;

                    return str($html)->toHtmlString();
                })
                ->schema([
                    TextInput::make('month')
                        ->live()
                        ->default(today()->day > 15 ? today()->startOfMonth()->format('Y-m') : today()->subMonth()->format('Y-m'))
                        ->type('month')
                        ->required(),
                    Select::make('period')
                        ->default(today()->day > 15 ? '1st' : 'full')
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
                    Group::make()
                        ->columns(2)
                        ->visible(fn (Get $get) => $get('period') === 'range')
                        ->schema([
                            DatePicker::make('from')
                                ->label('Start')
                                ->default(today()->day > 15 ? today()->startOfMonth()->format('Y-m-d') : today()->subMonth()->startOfMonth()->format('Y-m-d'))
                                ->validationAttribute('start')
                                ->minDate(fn (Get $get) => $get('month').'-01')
                                ->maxDate(fn (Get $get) => Carbon::parse($get('month'))->endOfMonth())
                                ->required()
                                ->dehydrated(false)
                                ->beforeOrEqual('to'),
                            DatePicker::make('to')
                                ->label('End')
                                ->default(today()->day > 15 ? today()->endOfMonth()->format('Y-m-d') : today()->subMonth()->setDay(15)->format('Y-m-d'))
                                ->validationAttribute('end')
                                ->minDate(fn (Get $get) => $get('month').'-01')
                                ->maxDate(fn (Get $get) => Carbon::parse($get('month'))->endOfMonth())
                                ->required()
                                ->dehydrated(false)
                                ->afterOrEqual('from'),
                        ]),
                    Repeater::make('dates')
                        ->visible(fn (Get $get) => $get('period') === 'dates')
                        ->required()
                        ->reorderable(false)
                        ->addActionLabel('Add a date')
                        ->grid(2)
                        ->simple(
                            DatePicker::make('date')
                                ->minDate(fn (Get $get) => $get('../../month').'-01')
                                ->maxDate(fn (Get $get) => Carbon::parse($get('../../month'))->endOfMonth())
                                ->markAsRequired()
                                ->rule('required')
                        ),
                    Group::make()
                        ->columns(2)
                        ->schema([
                            Select::make('format')
                                ->live()
                                ->placeholder('Print format')
                                ->default('csc')
                                ->required()
                                ->options(['default' => 'Default format', 'csc' => 'CSC format']),
                            Select::make('size')
                                ->live()
                                ->placeholder('Paper Size')
                                ->default('folio')
                                ->required()
                                ->options([
                                    'a4' => 'A4',
                                    'letter' => 'Letter',
                                    'folio' => 'Folio',
                                    'legal' => 'Legal',
                                ]),
                        ]),
                    Group::make()
                        ->columns(2)
                        ->schema([
                            Select::make('transmittal')
                                ->live()
                                ->default(0)
                                ->options([0, 1, 2, 3, 5])
                                ->in([0, 1, 2, 3, 5])
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('Input the number of copies of transmittal to be generated.'),
                            Select::make('grouping')
                                ->disabled(fn (Get $get) => $get('transmittal') <= 0)
                                ->default('offices')
                                ->options([
                                    'offices' => 'Office',
                                    false => 'None',
                                ])
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('
                                    Group transmittal by office or not.
                                    This will not affect the order of timesheets being generated.
                                    No grouping will generate a single transmittal for all selected employees.
                                '),
                        ]),
                    Checkbox::make('electronic_signature')
                        ->hintIcon('heroicon-o-check-badge')
                        ->hintIconTooltip('Electronically sign the document. This does not provide security against tampering.')
                        ->live()
                        ->afterStateUpdated(fn ($get, $set, $state) => $set('digital_signature', $state ? $get('digital_signature') : false))
                        ->rule(fn () => function ($attribute, $value, $fail) {
                            if ($value && ! Auth::user()->signature) {
                                $fail('Configure your electronic signature first');
                            }
                        }),
                ]),
        ];
    }
}
