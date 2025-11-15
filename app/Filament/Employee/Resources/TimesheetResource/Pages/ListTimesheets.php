<?php

namespace App\Filament\Employee\Resources\TimesheetResource\Pages;

use App\Enums\PaperSize;
use App\Filament\Actions\TableActions\BulkAction\GenerateTimesheetAction;
use App\Filament\Actions\TableActions\NavigateTimesheetAction;
use App\Filament\Employee\Resources\TimesheetResource;
use App\Filament\Employee\Widgets\ScannerStatisticsWidget;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class ListTimesheets extends ListRecords
{
    protected static string $resource = TimesheetResource::class;

    public function getBreadcrumb(): ?string
    {
        return Filament::auth()->user()->titled_name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            // NavigateTimesheetAction::make(),
            // $this->generate(),
            ActionGroup::make([
                // $this->generate(),
                $this->downloadBlankTimesheet(),
            ]),
        ];
    }

    public function getTabs(): array
    {
        return [
            'Monthly' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('span', 'full')),
            'Certified' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->certified()),
        ];
    }

    protected function generate(): Action
    {
        $generate = app(GenerateTimesheetAction::class, ['name' => 'generate-timesheet']);

        return Action::make('generate')
            ->icon('heroicon-o-bolt')
            ->requiresConfirmation()
            ->modalIconColor('danger')
            ->modalDescription($generate->generateConfirmation())
            ->form([
                TextInput::make('month')
                    ->default(today()->day > 15 ? today()->startOfMonth()->format('Y-m') : today()->subMonth()->format('Y-m'))
                    ->type('month')
                    ->required()
                    ->rule(fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                        if (empty($get('password')) || empty($get('confirm'))) {
                            return;
                        }

                        /** @var Employee */
                        $employee = Filament::auth()->user();

                        @[$year, $month] = explode('-', $value);

                        if ($employee->timelogs()->whereMonth('time', $month)->whereYear('time', $year)->doesntExist()) {
                            return $fail('No data found for the selected month.');
                        }

                        if ($employee->timesheets()->whereMonth('month', $month)->whereYear('month', $year)->exists()) {
                            return $fail('Timesheet already exists for the selected month.');
                        }
                    }),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->markAsRequired()
                    ->rules([
                        'required',
                        fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                            if ($value === $get('month')) {
                                return;
                            }

                            if (! password_verify($value, Auth::user()->password)) {
                                $fail('The password is incorrect');
                            }
                        },
                    ]),
                Checkbox::make('confirm')
                    ->label('I understand what I am doing')
                    ->markAsRequired()
                    ->accepted()
                    ->validationMessages(['accepted' => 'You must confirm that you understand what you are doing.']),
            ])
            ->action(fn (array $data) => $generate->generateAction(Auth::user(), $data));
    }

    protected function downloadBlankTimesheet(): Action
    {
        return Action::make('download-blank-timesheet')
            ->label('Download Blank Timesheet')
            ->requiresConfirmation()
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Download')
            ->modalIcon('heroicon-o-arrow-down')
            ->form([
                Select::make('size')
                    ->options(PaperSize::class)
                    ->default(PaperSize::FOLIO)
                    ->required()
                    ->helperText('The paper size of the blank timesheet.'),
                TextInput::make('month')
                    ->type('month')
                    ->required()
                    ->default(today()->format('Y-m'))
                    ->helperText('The month to be printed on the blank timesheet.'),
                Group::make([
                    Toggle::make('name')
                        ->default(true)
                        ->required()
                        ->helperText('Include your name on the blank timesheet.'),
                    Toggle::make('schedule')
                        ->default(true)
                        ->required()
                        ->helperText('Include your schedule on the blank timesheet.'),
                    Toggle::make('supervisor')
                        ->default(true)
                        ->required()
                        ->helperText('Include your supervisor\'s name on the blank timesheet.'),
                    Toggle::make('head')
                        ->default(true)
                        ->required()
                        ->helperText('Include the office head\'s name on the blank timesheet.'),
                    Toggle::make('weekends')
                        ->default(true)
                        ->required()
                        ->helperText('Label weekends on the blank timesheet.'),
                    Toggle::make('holidays')
                        ->default(true)
                        ->required()
                        ->helperText('Label holidays on the blank timesheet.'),
                    Toggle::make('single')
                        ->required()
                        ->helperText('Single timesheet per page.'),
                ])->columns(2),
            ])
            ->action(function (array $data) {
                $pdf = Pdf::view('print.blank', [
                    'employee' => Auth::user(),
                    'name' => $data['name'],
                    'month' => $data['month'],
                    'supervisor' => $data['supervisor'],
                    'head' => $data['head'],
                    'size' => $data['size']->value,
                    'weekends' => $data['weekends'],
                    'holidays' => $data['holidays'],
                    'single' => $data['single'],
                    'schedule' => $data['schedule'],
                ]);

                if ($data['size'] === PaperSize::FOLIO) {
                    [$width, $height] = PaperSize::FOLIO->getDimension('in');

                    $pdf->paperSize($width, $height, 'in');
                } else {
                    $pdf->format($data['size']->value);
                }

                $pdf->withBrowsershot(fn (Browsershot $browsershot) => $browsershot->noSandbox()->setOption('args', ['--disable-web-security']));

                $out = sys_get_temp_dir().'/'.uniqid().'.pdf';

                $pdf->save($out);

                return response()->download($out, 'blank (csc form 48).pdf', ['Content-Type' => 'application/pdf'])->deleteFileAfterSend();
            });
    }

    protected function getFooterWidgets(): array
    {
        return [
            ScannerStatisticsWidget::class,
        ];
    }
}
