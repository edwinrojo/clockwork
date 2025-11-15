<?php

namespace App\Filament\Superuser\Resources\EmployeeResource\RelationManagers;

use App\Filament\Filters\ActiveFilter;
use App\Models\Deployment;
use App\Models\Office;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class OfficesRelationManager extends RelationManager
{
    protected static string $relationship = 'deployments';

    protected static ?string $title = 'Office Deployment';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('office_id')
                    ->live()
                    ->relationship('office', 'name')
                    ->searchable()
                    ->preload()
                    ->disabledOn('edit')
                    ->required()
                    ->columnSpanFull()
                    ->validationMessages(['unique' => 'Employee is already deployed to this office.'])
                    ->afterStateUpdated(fn (Set $set) => $set('supervisor_id', null))
                    ->rules([
                        fn (?Deployment $record) => Rule::unique('deployment', 'office_id')
                            ->where('employee_id', $this->ownerRecord->id)
                            ->ignore($record?->id, 'id'),
                    ]),
                Select::make('supervisor_id')
                    ->relationship('supervisor', 'name', function ($query, $record, $get) {
                        if (! isset($record)) {
                            $query->whereHas('offices', function ($query) use ($get) {
                                $query->where('offices.id', $get('office_id'))
                                    ->where('deployment.current', true)
                                    ->where('deployment.active', true);
                            });

                            return;
                        }

                        $query->whereNotIn('employees.id', [$record->employee_id, $record->office->head?->id]);

                        $query->whereHas('offices', function ($query) use ($record) {
                            $query->where('offices.id', $record->office_id)
                                ->where('active', true);
                        });
                    })
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->validationMessages(['unique' => 'Employee is already deployed to this office.'])
                    ->rules([
                        fn (?Deployment $record) => Rule::unique('deployment', 'office_id')
                            ->where('employee_id', $this->ownerRecord->id)
                            ->ignore($record?->id, 'id'),
                    ]),
                ToggleButtons::make('active')
                    ->boolean()
                    ->inline()
                    ->grouped()
                    ->required()
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('office.code')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('supervisor.name')
                    ->formatStateUsing(fn ($record) => $record->supervisor?->titled_name)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('current')
                    ->getStateUsing(fn ($record) => $record->current ? 'Yes' : 'No')
                    ->icon(fn ($record) => $record->current ? 'heroicon-o-check' : 'heroicon-o-no-symbol')
                    ->toggleable(),
                TextColumn::make('active')
                    ->getStateUsing(fn ($record) => $record->active ? 'Yes' : 'No')
                    ->icon(fn ($record) => $record->active ? 'heroicon-o-check' : 'heroicon-o-no-symbol')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ActiveFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->createAnother(false)
                    ->slideOver()
                    ->modalWidth('xl'),
            ])
            ->recordActions([
                Action::make('Current')
                    ->disabled(fn ($record) => $record->current)
                    ->requiresConfirmation()
                    ->icon('heroicon-o-check-badge')
                    ->modalIcon('heroicon-o-check-badge')
                    ->modalDescription('Set this deployment as the current office for this employee?')
                    ->action(function (Deployment $record) {
                        Deployment::where('employee_id', $record->employee_id)->update(['current' => false]);

                        $record->update(['current' => true]);
                    }),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('xl'),
                DeleteAction::make()
                    ->icon('heroicon-o-x-circle')
                    ->modalIcon('heroicon-o-shield-exclamation'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->icon('heroicon-o-x-circle')
                        ->modalIcon('heroicon-o-shield-exclamation'),
                ]),
            ])
            ->defaultSort(function ($query) {
                $query->orderBy('current', 'desc');

                $query->orderBy(
                    Office::select('active')
                        ->whereColumn('offices.id', 'deployment.id')
                        ->limit(1),
                    'asc'
                );

                $query->orderBy(
                    Office::select('code')
                        ->whereColumn('offices.id', 'deployment.id')
                        ->limit(1),
                    'asc'
                );
            })
            ->recordAction(null);
    }
}
