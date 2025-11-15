<?php

namespace App\Filament\Superuser\Resources\EmployeeResource\RelationManagers;

use App\Filament\Filters\ActiveFilter;
use App\Models\Enrollment;
use App\Models\Scanner;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class ScannersRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Scanner Enrollment';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('scanner_id')
                    ->relationship('scanner', 'name', function ($query) {
                        if (! in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['superuser', 'manager'])) {
                            $query->whereIn('scanners.id', user()->scanners->pluck('id'));
                        }

                        $query->whereNotIn('scanners.id', $this->ownerRecord->enrollments()->select('scanner_id'));

                        $query->reorder()->orderBy('priority', 'desc')->orderBy('name');
                    })
                    ->preload()
                    ->searchable()
                    ->disabledOn('edit')
                    ->required()
                    ->columnSpanFull()
                    ->validationMessages(['unique' => 'Employee has already been enrolled to this scanner.'])
                    ->rules([
                        fn (?Enrollment $record) => Rule::unique('enrollment', 'scanner_id')
                            ->where('employee_id', $this->ownerRecord->id)
                            ->ignore($record?->id, 'id'),
                    ]),
                TextInput::make('uid')
                    ->markAsRequired()
                    ->label('UID')
                    ->rules('required')
                    ->maxLength(255)
                    ->validationAttribute('uid')
                    ->rules([
                        fn (Get $get, ?Enrollment $record) => Rule::unique('enrollment', 'uid')
                            ->where('scanner_id', $record?->scanner_id ?? $get('scanner_id'))
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
            ->modifyQueryUsing(fn ($query) => $query->whereHas('scanner', fn ($q) => $q->whereActive(1)))
            ->columns([
                TextColumn::make('scanner.name')
                    ->label('Name')
                    ->placeholder(fn ($record) => $record->id)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uid')
                    ->label('UID')
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw("CAST(uid as UNSIGNED) $direction"))
                    ->searchable(query: fn ($query, $search) => $query->where('uid', $search)),
                TextColumn::make('active')
                    ->getStateUsing(fn ($record) => $record->active ? 'Yes' : 'No')
                    ->icon(fn ($record) => $record->active ? 'heroicon-o-check' : 'heroicon-o-no-symbol')
                    ->toggleable(),
            ])
            ->filters([
                ActiveFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->createAnother(false)
                    ->slideOver()
                    ->modalWidth('xl')
                    ->visible(fn () => user()->scanners->isNotEmpty()),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('xl')
                    ->visible(function (Enrollment $record) {
                        return in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['superuser', 'manager']) ?:
                            user()->scanners->first(fn ($scanner) => $scanner->id === $record->scanner_id);
                    }),
                DeleteAction::make()
                    ->icon('heroicon-o-x-circle')
                    ->modalIcon('heroicon-o-shield-exclamation')
                    ->visible(function (Enrollment $record) {
                        return in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['superuser', 'manager']) ?:
                            user()->scanners->first(fn ($scanner) => $scanner->id === $record->scanner_id);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->icon('heroicon-o-x-circle')
                        ->modalIcon('heroicon-o-shield-exclamation')
                        ->visible(fn () => in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['superuser'])),
                ]),
            ])
            ->defaultSort(function ($query) {
                $query->orderBy(
                    Scanner::select('priority')
                        ->whereColumn('scanners.id', 'enrollment.scanner_id')
                        ->limit(1),
                    'desc'
                );

                $query->orderBy(
                    Scanner::select('active')
                        ->whereColumn('scanners.id', 'enrollment.scanner_id')
                        ->limit(1),
                    'asc'
                );

                $query->orderBy(
                    Scanner::select('name')
                        ->whereColumn('scanners.id', 'enrollment.scanner_id')
                        ->limit(1),
                    'asc'
                );
            })
            ->recordAction(null);
    }
}
