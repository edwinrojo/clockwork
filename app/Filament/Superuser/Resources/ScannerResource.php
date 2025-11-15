<?php

namespace App\Filament\Superuser\Resources;

use App\Filament\Actions\TableActions\FetchAction;
use App\Filament\Filters\ActiveFilter;
use App\Filament\Superuser\Resources\ScannerResource\Pages\CreateScanner;
use App\Filament\Superuser\Resources\ScannerResource\Pages\EditScanner;
use App\Filament\Superuser\Resources\ScannerResource\Pages\ListScanners;
use App\Filament\Superuser\Resources\ScannerResource\RelationManagers\EmployeesRelationManager;
use App\Filament\Superuser\Resources\ScannerResource\RelationManagers\UsersRelationManager;
use App\Models\Scanner;
use App\Models\Scopes\ActiveScope;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class ScannerResource extends Resource
{
    protected static ?string $model = Scanner::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-touch-app-o';

    protected static ?string $recordTitleAttribute = 'name';

    public static function formSchema(): array
    {
        return [
            Section::make('Scanner Details')
                ->columns()
                ->schema([
                    ToggleButtons::make('priority')
                        ->required()
                        ->boolean()
                        ->grouped()
                        ->inline()
                        ->default(false)
                        ->helperText('Prioritized scanners have higher precedence over others.'),
                    ToggleButtons::make('active')
                        ->required()
                        ->boolean()
                        ->grouped()
                        ->inline()
                        ->default(false)
                        ->helperText('Inactive scanners will be ignored.'),
                    TextInput::make('name')
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (string $state): ?string => mb_strtolower($state)),
                    TextInput::make('uid')
                        ->hint('Device ID')
                        ->label('UID')
                        ->validationAttribute('uid')
                        ->numeric()
                        ->type('text')
                        ->unique(ignoreRecord: true)
                        ->rules(['required', 'min:2', 'max:255'])
                        ->markAsRequired()
                        ->dehydrateStateUsing(fn (?string $state): ?int => (int) $state),
                    Textarea::make('remarks')
                        ->columnSpanFull(),
                ]),
            Section::make('Printout Configuration')
                ->columns()
                ->schema([
                    ColorPicker::make('print.foreground_color')
                        ->rgba()
                        ->label('Foreground Color')
                        ->default('rgba(0, 0, 0, 1)')
                        ->helperText('The color of the text in the printout.'),
                    ColorPicker::make('print.background_color')
                        ->rgba()
                        ->label('Background Color')
                        ->default('rgba(0, 0, 0, 0)')
                        ->helperText('The color of the background in the printout.'),
                ]),
            Section::make('Connection Parameters')
                ->columns(3)
                ->visible(! config('app.remote.server') ?: config('app.remote.host') && config('app.remote.key') && config('app.remote.token') && config('app.remote.user'))
                ->schema([
                    TextInput::make('host')
                        ->unique(ignoreRecord: true)
                        ->requiredWith('port')
                        ->helperText('The hostname or IP address of the scanner.'),
                    TextInput::make('port')
                        ->numeric()
                        ->type('text')
                        ->helperText('The port number of the scanner.'),
                    TextInput::make('pass')
                        ->password()
                        ->helperText('The password of the scanner.'),
                ]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::formSchema());
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
                ActiveFilter::make(),
                TrashedFilter::make()
                    ->native(false),
            ])
            ->recordActions([
                // Tables\Actions\ActionGroup::make([
                EditAction::make(),
                FetchAction::make(),
                // ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('Set active state')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->groupedIcon('heroicon-o-check-circle')
                        ->schema([
                            Section::make([
                                Radio::make('active')
                                    ->boolean()
                                    ->inline()
                                    ->inlineLabel(false)
                                    ->required(),
                            ]),
                        ])
                        ->action(function (BulkAction $action, Collection $records, array $data) {
                            $records->toQuery()->update(['active' => $data['active']]);

                            $action->deselectRecordsAfterCompletion();

                            $label = $records->count() > 1 ? static::getPluralModelLabel() : static::getModelLabel();

                            Notification::make()
                                ->success()
                                ->title('Active state updated')
                                ->body($records->count()." $label has been set to ".($data['active'] ? 'active' : 'inactive').'.')
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->recordUrl(null)
            ->deferLoading()
            ->defaultSort(function (Builder $query) {
                $query->orderBy('priority', 'desc');

                $query->orderByRaw('uid is NOT NULL desc');

                $query->orderBy('name');
            });
    }

    public static function getRelations(): array
    {
        return [
            EmployeesRelationManager::class,
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScanners::route('/'),
            'create' => CreateScanner::route('/create'),
            'edit' => EditScanner::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                ActiveScope::class,
                SoftDeletingScope::class,
            ]);
    }
}
