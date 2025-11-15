<?php

namespace App\Filament\Secretary\Resources;

use App\Filament\Actions\TableActions\FetchAction;
use App\Filament\Secretary\Resources\ScannerResource\Pages\EditScanner;
use App\Filament\Secretary\Resources\ScannerResource\Pages\ListScanners;
use App\Filament\Superuser\Resources\ScannerResource as SuperuserScannerResource;
use App\Filament\Superuser\Resources\ScannerResource\RelationManagers\EmployeesRelationManager;
use App\Models\Scanner;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ScannerResource extends Resource
{
    protected static ?string $model = Scanner::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-touch-app-o';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(SuperuserScannerResource::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uid')
                    ->extraCellAttributes(['class' => 'font-mono'])
                    ->placeholder('<blank>')
                    ->label('UID')
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw("CAST(uid as INT) $direction"))
                    ->searchable(query: fn ($query, $search) => $query->whereRaw("CAST(uid as TEXT) = '$search'")),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('employees_count')
                    ->label('Employees')
                    ->counts(['employees' => fn ($query) => $query->where('enrollment.active', true)])
                    ->sortable(),
                TextColumn::make('timelogs_count')
                    ->label('Timelogs')
                    ->counts('timelogs')
                    ->sortable(),
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
                //
            ])
            ->recordActions([
                EditAction::make(),
                FetchAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->schema([
                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->currentPassword()
                                ->markAsRequired()
                                ->rules(['required', 'string']),
                        ]),
                ]),
            ])
            ->deferLoading();
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
            'index' => ListScanners::route('/'),
            'edit' => EditScanner::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('scanners.id', Auth::user()->scanners?->pluck('id')->toArray());
    }
}
