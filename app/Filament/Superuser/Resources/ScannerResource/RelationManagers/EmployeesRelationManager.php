<?php

namespace App\Filament\Superuser\Resources\ScannerResource\RelationManagers;

use App\Filament\Filters\ActiveFilter;
use App\Models\Enrollment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Employee Enrollment';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->preload()
                    ->searchable()
                    ->disabledOn('edit')
                    ->required()
                    ->columnSpanFull()
                    ->validationMessages(['unique' => 'Employee has already been enrolled to this scanner.'])
                    ->rules([
                        fn (?Enrollment $record) => Rule::unique('enrollment', 'employee_id')
                            ->where('scanner_id', $this->ownerRecord->id)
                            ->ignore($record?->id, 'id'),
                    ]),
                TextInput::make('uid')
                    ->markAsRequired()
                    ->label('UID')
                    ->rules('required')
                    ->maxLength(255)
                    ->validationAttribute('UID')
                    ->rules([
                        fn (Get $get) => Rule::unique('enrollment', 'uid')
                            ->where('scanner_id', $this->ownerRecord->id)
                            ->ignore($get('employee_id'), 'employee_id'),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('employee', fn ($q) => $q->where('active', 1)))
            ->columns([
                TextColumn::make('uid')
                    ->label('UID')
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw("CAST(uid as INT) $direction"))
                    ->searchable(query: fn ($query, $search) => $query->where('uid', $search)),
                TextColumn::make('employee.full_name')
                    ->label('Name')
                    ->placeholder(fn ($record) => $record->employee_id)
                    ->searchable(),
                TextColumn::make('status')
                    ->toggleable()
                    ->getStateUsing(function (Enrollment $record): string {
                        return str($record->employee->status?->value)
                            ->title()
                            ->when($record->employee->substatus?->value, function ($status) use ($record) {
                                return $status->append(" ({$record->employee->substatus->value})")->replace('_', '-')->title();
                            });
                    }),
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
            ->defaultSort(function (Builder $query) {
                $query->orderByRaw('CAST(uid as INT) asc');
            })
            ->recordAction(null);
    }
}
