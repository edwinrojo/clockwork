<?php

namespace App\Filament\Secretary\Resources;

use App\Filament\Secretary\Resources\OfficeResource\Pages\EditOffice;
use App\Filament\Secretary\Resources\OfficeResource\Pages\ListOffices;
use App\Filament\Secretary\Resources\OfficeResource\RelationManagers\EmployeesRelationManager;
use App\Filament\Superuser\Resources\OfficeResource as SuperuserOfficeResource;
use App\Models\Office;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-corporate-fare-o';

    protected static ?string $recordTitleAttribute = 'name';

    public static function formSchema(): array
    {
        return SuperuserOfficeResource::formSchema(head: true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employees_count')
                    ->label('Employees')
                    ->counts('employees')
                    ->toggleable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                ]),
            ])
            ->deferLoading()
            ->recordUrl(null);
    }

    public static function getRelations(): array
    {
        return [
            EmployeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffices::route('/'),
            'edit' => EditOffice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('offices.id', user()->offices?->pluck('id')->toArray());
    }
}
