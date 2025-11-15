<?php

namespace App\Filament\Superuser\Resources\ScheduleResource\RelationManagers;

use App\Enums\WorkArrangement;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Employee Shifts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Time')
                    ->schema([
                        TimePicker::make('timetable.p1.time')
                            ->markAsRequired()
                            ->seconds(false)
                            ->label('Punch 1')
                            ->hint('In')
                            ->live()
                            ->default('08:00')
                            ->afterStateUpdated(function (Set $set, string $state) {
                                $set('timetable.p2', today()->setTime(...explode(':', $state))->addHours(intval($this->ownerRecord->timetable['duration']))->format('H:i'));
                            })
                            ->rules([
                                'date_format:H:i',
                                'required',
                                fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                                    if (empty($get('timetable.p2'))) {
                                        return;
                                    }

                                    $p2 = today()->setTime(...explode(':', $get('timetable.p2.time')));

                                    if ($p2->subHours(intval($this->ownerRecord->timetable['duration']))->format('H:i') != $value) {
                                        $fail('The total number of work hours must be equal to work hour duration set in the schedule.');
                                    }
                                },
                            ]),
                        TimePicker::make('timetable.p2.time')
                            ->markAsRequired()
                            ->seconds(false)
                            ->label('Punch 2')
                            ->hint('Out')
                            ->live()
                            ->default('16:00')
                            ->afterStateUpdated(function (Set $set, string $state) {
                                $set('timetable.p1', today()->setTime(...explode(':', $state))->subHours(intval($this->ownerRecord->timetable['duration']))->format('H:i'));
                            })
                            ->rules([
                                'date_format:H:i',
                                'required',
                                fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                                    if (empty($get('timetable.p1'))) {
                                        return;
                                    }

                                    $p1 = today()->setTime(...explode(':', $get('timetable.p1.time')));

                                    if ($p1->addHours(intval($this->ownerRecord->timetable['duration']))->format('H:i') != $value) {
                                        $fail('The total number of work hours must be equal to work hour duration set in the schedule.');
                                    }
                                },
                            ]),
                    ]),
                Fieldset::make('Alias')
                    ->schema([
                        Select::make('timetable.p1.alias')
                            ->live()
                            ->label('Punch 1')
                            ->hint('in')
                            ->hintIcon('heroicon-m-question-mark-circle')
                            ->hintIconTooltip('The alias for the punch 1 field. This will affect how the attendance will reflect in the reports.')
                            ->required()
                            ->default('p1')
                            ->options([
                                'p1' => 'Punch 1',
                                'p3' => 'Punch 3',
                            ])
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state == 'p3') {
                                    $set('timetable.alias.p2', 'p4');
                                }
                            }),
                        Select::make('timetable.p2.alias')
                            ->label('Punch 2')
                            ->hint('out')
                            ->hintIcon('heroicon-m-question-mark-circle')
                            ->hintIconTooltip('The alias for the punch 2 field. This will affect how the attendance will reflect in the reports.')
                            ->required()
                            ->default('p4')
                            ->options([
                                'p2' => 'Punch 2',
                                'p4' => 'Punch 4',
                            ])
                            ->disableOptionWhen(function (string $value, Get $get) {
                                return $get('timetable.alias.p1') == 'p3' && $value == 'p2';
                            }),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('timetable.p1')
                    ->visible(fn () => $this->ownerRecord->arrangement == WorkArrangement::WORK_SHIFTING->value)
                    ->label('In')
                    ->extraCellAttributes(['class' => 'font-mono']),
                TextColumn::make('timetable.p2')
                    ->visible(fn () => $this->ownerRecord->arrangement == WorkArrangement::WORK_SHIFTING->value)
                    ->label('Out')
                    ->extraCellAttributes(['class' => 'font-mono']),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->slideOver()
                    ->preloadRecordSelect()
                    ->multiple()
                    ->modalWidth('xl')
                    ->label('New shift')
                    ->modalHeading('Add employees to schedule')
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        return $query->whereHas('offices', fn (Builder $query) => $query->where('offices.id', $this->ownerRecord->office_id));
                    })
                    ->form(fn (AttachAction $action) => [
                        Group::make([
                            Fieldset::make('Alias')
                                ->schema([
                                    Select::make('timetable.p1.alias')
                                        ->live()
                                        ->label('Punch 1')
                                        ->hint('in')
                                        ->hintIcon('heroicon-m-question-mark-circle')
                                        ->hintIconTooltip('The alias for the punch 1 field. This will affect how the attendance will reflect in the reports.')
                                        ->required()
                                        ->default('p1')
                                        ->options([
                                            'p1' => 'Punch 1',
                                            'p3' => 'Punch 3',
                                        ])
                                        ->afterStateUpdated(function (Set $set, ?string $state) {
                                            if ($state == 'p3') {
                                                $set('timetable.alias.p2', 'p4');
                                            }
                                        }),
                                    Select::make('timetable.p2.alias')
                                        ->label('Punch 2')
                                        ->hint('out')
                                        ->hintIcon('heroicon-m-question-mark-circle')
                                        ->hintIconTooltip('The alias for the punch 2 field. This will affect how the attendance will reflect in the reports.')
                                        ->required()
                                        ->default('p4')
                                        ->options([
                                            'p2' => 'Punch 2',
                                            'p4' => 'Punch 4',
                                        ])
                                        ->disableOptionWhen(function (string $value, Get $get) {
                                            return $get('timetable.p1.alias') == 'p3' && $value == 'p2';
                                        }),
                                ]),
                            Fieldset::make('Time')
                                ->schema([
                                    TimePicker::make('timetable.p1.time')
                                        ->markAsRequired()
                                        ->seconds(false)
                                        ->label('Punch 1')
                                        ->hint('In')
                                        ->live()
                                        ->default('08:00')
                                        ->afterStateUpdated(function (Set $set, string $state) {
                                            $set('timetable.p2.time', today()->setTime(...explode(':', $state))->addHours(intval($this->ownerRecord->timetable['duration']))->format('H:i'));
                                        })
                                        ->rules([
                                            'date_format:H:i',
                                            'required',
                                            fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                                                if (empty($get('timetable.p2.time'))) {
                                                    return;
                                                }

                                                $p2 = today()->setTime(...explode(':', $get('timetable.p2.time')));

                                                if ($p2->subHours(intval($this->ownerRecord->timetable['duration']))->format('H:i') != $value) {
                                                    $fail('The total number of work hours must be equal to work hour duration set in the schedule.');
                                                }
                                            },
                                        ]),
                                    TimePicker::make('timetable.p2.time')
                                        ->markAsRequired()
                                        ->seconds(false)
                                        ->label('Punch 2')
                                        ->hint('Out')
                                        ->live()
                                        ->default('16:00')
                                        ->afterStateUpdated(function (Set $set, string $state) {
                                            $set('timetable.p1.time', today()->setTime(...explode(':', $state))->subHours(intval($this->ownerRecord->timetable['duration']))->format('H:i'));
                                        })
                                        ->rules([
                                            'date_format:H:i',
                                            'required',
                                            fn (Get $get) => function ($attribute, $value, $fail) use ($get) {
                                                if (empty($get('timetable.p1.time'))) {
                                                    return;
                                                }

                                                $p1 = today()->setTime(...explode(':', $get('timetable.p1.time')));

                                                if ($p1->addHours(intval($this->ownerRecord->timetable['duration']))->format('H:i') != $value) {
                                                    $fail('The total number of work hours must be equal to work hour duration set in the schedule.');
                                                }
                                            },
                                        ]),
                                ]),
                        ])->visible(fn () => $this->ownerRecord->arrangement == WorkArrangement::WORK_SHIFTING->value),
                        Fieldset::make('On-shift')
                            ->columns(1)
                            ->schema([
                                $action->getRecordSelect()
                                    ->hiddenLabel(false)
                                    ->label('Employee'),
                            ]),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => $this->ownerRecord->arrangement == WorkArrangement::WORK_SHIFTING->value)
                    ->slideOver()
                    ->mountUsing(fn ($record, $form) => $form->fill($record->pivot->toArray()))
                    ->modalWidth('xl'),
                DetachAction::make()
                    ->icon('heroicon-o-x-circle')
                    ->modalIcon('heroicon-o-shield-exclamation')
                    ->label('Remove')
                    ->modalHeading(fn ($record) => "Remove {$record->titled_name} from the schedule"),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->icon('heroicon-o-x-circle')
                        ->modalIcon('heroicon-o-shield-exclamation')
                        ->modalHeading('Remove selected employees from the schedule'),
                ]),
            ])
            ->deselectAllRecordsWhenFiltered(false)
            ->recordAction(null);
    }
}
