<?php

namespace App\Filament\Developer\Resources;

use App\Enums\UserRole;
use App\Filament\Developer\Resources\RouteResource\Pages\CreateRoute;
use App\Filament\Developer\Resources\RouteResource\Pages\EditRoute;
use App\Filament\Developer\Resources\RouteResource\Pages\ListRoutes;
use App\Models\Route;
use App\Models\Schedule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RouteResource extends Resource
{
    protected static ?string $model = Route::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-route-o';

    public static function form(Schema $schema): Schema
    {
        return $form
            ->schema([
                Section::make('Route information')
                    ->schema([
                        Select::make('model')
                            ->options([
                                Schedule::class => 'Schedule',
                            ])
                            ->required(),
                        Repeater::make('path')
                            ->grid(4)
                            ->simple(
                                Select::make('role')
                                    ->label('Role')
                                    ->options(UserRole::requestable())
                                    ->required(),
                            ),
                        Repeater::make('escalation')
                            ->grid(4)
                            ->simple(
                                Select::make('role')
                                    ->label('Role')
                                    ->options(UserRole::requestable())
                                    ->required(),
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('model')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (Route $record) => class_basename($record->model)),
                TextColumn::make('path')
                    ->getStateUsing(fn (Route $record) => collect($record->path)->map(fn ($path) => UserRole::tryFrom($path)->getLabel())->join(', ')),
                TextColumn::make('escalation')
                    ->getStateUsing(fn (Route $record) => collect($record->escalation)->map(fn ($target) => UserRole::tryFrom($target)->getLabel())->join(', ')),
            ])
            ->filters([
                TrashedFilter::make()
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->recordUrl(null);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoutes::route('/'),
            'create' => CreateRoute::route('/create'),
            'edit' => EditRoute::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
