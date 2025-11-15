<?php

namespace App\Filament\Superuser\Resources\ScannerResource\RelationManagers;

use App\Filament\Filters\ActiveFilter;
use App\Models\Assignment;
use App\Models\Office;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'assignees';

    protected static ?string $title = 'User assignment';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->disabledOn('edit')
                    ->required()
                    ->columnSpanFull()
                    ->validationMessages(['unique' => 'Employee is already deployed to this office.'])
                    ->rules([
                        fn (?Assignment $record) => Rule::unique(Assignment::class, 'user_id')
                            ->where('assignable_id', $this->ownerRecord->id)
                            ->where('assignable_type', Office::class)
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
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
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
            ->recordAction(null);
    }
}
