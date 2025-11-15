<?php

namespace App\Filament\Secretary\Resources;

use App\Filament\Secretary\Resources\GroupResource\Pages\CreateGroup;
use App\Filament\Secretary\Resources\GroupResource\Pages\EditGroup;
use App\Filament\Secretary\Resources\GroupResource\Pages\ListGroups;
use App\Filament\Superuser\Resources\GroupResource\RelationManagers\EmployeesRelationManager;
use App\Models\Group;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-diversity-2-o';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Group name')
                ->schema([
                    TextInput::make('name')
                        ->hiddenLabel()
                        ->alphaDash()
                        ->required()
                        ->columnSpanFull()
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (string $state): ?string => mb_strtolower($state)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employees_count')
                    ->label('Employees')
                    ->counts(['employees' => function ($query) {
                        $query->where('member.active', true);

                        $query->where(function (Builder $query) {
                            $query->orWhereHas('offices', function (Builder $query) {
                                $query->whereIn('offices.id', user()->offices->pluck('id'));
                            });

                            $query->orWhereHas('scanners', function (Builder $query) {
                                $query->whereIn('scanners.id', user()->scanners->pluck('id'));
                            });
                        });
                    }])
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
            'index' => ListGroups::route('/'),
            'create' => CreateGroup::route('/create'),
            'edit' => EditGroup::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->where(function ($query) {
                $query->whereHas('employees', function ($query) {
                    $query->where(function ($query) {
                        $query->orWhereHas('scanners', function ($query) {
                            $query->whereIn('scanners.id', user()->scanners->pluck('id'));
                        });

                        $query->orWhereHas('offices', function ($query) {
                            $query->whereIn('offices.id', user()->offices->pluck('id'));
                        });
                    });

                    $query->where('employees.active', true);
                });

                $query->orWhereDoesntHave('employees');
            });
    }
}
