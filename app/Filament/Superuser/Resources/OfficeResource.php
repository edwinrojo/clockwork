<?php

namespace App\Filament\Superuser\Resources;

use App\Enums\UserRole;
use App\Filament\Superuser\Resources\OfficeResource\Pages\CreateOffice;
use App\Filament\Superuser\Resources\OfficeResource\Pages\EditOffice;
use App\Filament\Superuser\Resources\OfficeResource\Pages\ListOffices;
use App\Filament\Superuser\Resources\OfficeResource\RelationManagers\EmployeesRelationManager;
use App\Filament\Superuser\Resources\OfficeResource\RelationManagers\UsersRelationManager;
use App\Models\Employee;
use App\Models\Office;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-corporate-fare-o';

    protected static ?string $recordTitleAttribute = 'name';

    public static function formSchema(bool $head = false): array
    {
        $self = @debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2)[1]['class'] === get_called_class();

        $panel = Filament::getCurrentOrDefaultPanel()->getId();

        return [
            Section::make('General information')
                ->columns(5)
                ->schema([
                    FileUpload::make('logo')
                        ->helperText('The office\'s official logo.')
                        ->visibility('public')
                        ->getUploadedFileNameForStorageUsing(fn ($file, $get) => 'offices/'.mb_strtolower($get('code')).'.'.$file->extension())
                        ->imageEditor()
                        ->avatar()
                        ->maxSize(2048),
                    Group::make([
                        TextInput::make('name')
                            ->required()
                            ->helperText('The full expanded name of the office.'),
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('The shorthand name of the office.'),
                    ])->columnSpan(4),
                ]),
            Section::make('Office head')
                ->hiddenOn(['create'])
                ->schema([
                    Select::make('head')
                        ->relationship('head', 'full_name', fn ($query, $record) => $query->whereHas('offices', fn ($q) => $q->where('offices.id', $record?->id)))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull()
                        ->nullable()
                        ->hiddenLabel()
                        ->editOptionForm($self ? EmployeeResource::formSchema() : null)
                        ->createOptionForm($self ? EmployeeResource::formSchema() : null)
                        ->visible(fn () => $self || $head)
                        ->disabled(function (Get $get) use ($panel) {
                            if ($panel === 'superuser') {
                                return false;
                            }

                            return Employee::find($get('head'))?->user?->hasRole(UserRole::DIRECTOR);
                        })
                        ->helperText(function (Get $get) use ($panel) {
                            if ($panel === 'superuser') {
                                return null;
                            }

                            return Employee::find($get('head'))?->user?->hasRole(UserRole::DIRECTOR)
                                ? 'This office is currently handled actively by the selected employee.'
                                : null;
                        }),
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
                TrashedFilter::make()
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
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
            ->deferLoading()
            ->recordUrl(null);
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
            'index' => ListOffices::route('/'),
            'create' => CreateOffice::route('/create'),
            'edit' => EditOffice::route('/{record}/edit'),
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
