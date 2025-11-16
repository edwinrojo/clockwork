<?php

namespace App\Filament\Superuser\Resources;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentSubstatus;
use App\Filament\Filters\ActiveFilter;
use App\Filament\Filters\OfficeFilter;
use App\Filament\Filters\StatusFilter;
use App\Filament\Superuser\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Superuser\Resources\EmployeeResource\Pages\EditEmployee;
use App\Filament\Superuser\Resources\EmployeeResource\Pages\ListEmployees;
use App\Filament\Superuser\Resources\EmployeeResource\RelationManagers\GroupsRelationManager;
use App\Filament\Superuser\Resources\EmployeeResource\RelationManagers\OfficesRelationManager;
use App\Filament\Superuser\Resources\EmployeeResource\RelationManagers\ScannersRelationManager;
use App\Models\Employee;
use App\Models\Scopes\ActiveScope;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-badge-o';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function formSchema(bool $compact = false): array
    {
        $isCalledBySelf = @debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2)[1]['class'] === get_called_class();

        return [
            Section::make('Personal Information')
                ->compact($compact)
                ->columns(2)
                ->schema([
                    TextInput::make('last_name')
                        ->helperText('Family name or surname of the employee.')
                        ->minLength(2)
                        ->markAsRequired()
                        ->rules('required')
                        ->rule(fn (?Employee $record, Get $get) => function ($attribute, $value, $fail) use ($get, $record) {
                            $employee = Employee::withoutGlobalScopes()
                                ->whereNot('id', $record?->id)
                                ->where([
                                    'last_name' => $get('last_name'),
                                    'first_name' => $get('first_name'),
                                ])->when($get('middle_name') === 'N/A', function ($query) {
                                    return $query->where(function ($query) {
                                        $query->where('middle_name', '')
                                            ->orWhereNull('middle_name');
                                    });
                                }, function ($query) use ($get) {
                                    return $query->where('middle_name', $get('middle_name'));
                                })->when($get('qualifier_name') === 'N/A', function ($query) {
                                    return $query->where(function ($query) {
                                        $query->where('qualifier_name', '')
                                            ->orWhereNull('qualifier_name');
                                    });
                                }, function ($query) use ($get) {
                                    return $query->where('qualifier_name', $get('qualifier_name'));
                                });

                            if ($employee->exists()) {
                                $fail('This exact employee already exists.');
                            }
                        }),
                    TextInput::make('first_name')
                        ->helperText('Given name or forename of the employee.')
                        ->minLength(2)
                        ->markAsRequired()
                        ->rules('required')
                        ->rule(fn (?Employee $record, Get $get) => function ($attribute, $value, $fail) use ($get, $record) {
                            $employee = Employee::withoutGlobalScopes()
                                ->whereNot('id', $record?->id)
                                ->where([
                                    'last_name' => $get('last_name'),
                                    'first_name' => $get('first_name'),
                                ])->when($get('middle_name') === 'N/A', function ($query) {
                                    return $query->where(function ($query) {
                                        $query->where('middle_name', '')
                                            ->orWhereNull('middle_name');
                                    });
                                }, function ($query) use ($get) {
                                    return $query->where('middle_name', $get('middle_name'));
                                })->when($get('qualifier_name') === 'N/A', function ($query) {
                                    return $query->where(function ($query) {
                                        $query->where('qualifier_name', '')
                                            ->orWhereNull('qualifier_name');
                                    });
                                }, function ($query) use ($get) {
                                    return $query->where('qualifier_name', $get('qualifier_name'));
                                });

                            if ($employee->exists()) {
                                $fail('This exact employee already exists.');
                            }
                        }),
                    TextInput::make('middle_name')
                        ->helperText('Middle name or additional name of the employee usually derived from the mother\'s maiden name.')
                        ->visibleOn('view'),
                    TextInput::make('middle_name')
                        ->helperText('Middle name or additional name of the employee usually derived from the mother\'s maiden name.')
                        ->hintAction(
                            Action::make('na')
                                ->label('n/a')
                                ->icon('heroicon-o-no-symbol')
                                ->extraAttributes(['tabindex' => -1])
                                ->action(fn (Set $set) => $set('middle_name', 'N/A'))
                        )
                        ->dehydrateStateUsing(fn ($state) => $state === 'N/A' ? '' : $state)
                        ->markAsRequired()
                        ->rules('required')
                        ->hiddenOn('view')
                        ->minLength(2),
                    Select::make('qualifier_name')
                        ->helperText('Qualifier name or name extension to distinguish an individual from others who may have the same name.')
                        ->options([
                            'N/A' => 'N/A',
                            'Jr' => 'Jr.',
                            'Sr' => 'Sr.',
                            'II' => 'II',
                            'III' => 'III',
                            'IV' => 'IV',
                            'V' => 'V',
                            'VI' => 'VI',
                            'VII' => 'VII',
                            'VIII' => 'VIII',
                            'IX' => 'IX',
                            'X' => 'X',
                        ])
                        ->in(['N/A', 'Jr', 'Sr', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'])
                        ->hintAction(
                            Action::make('na')
                                ->label('n/a')
                                ->icon('heroicon-o-no-symbol')
                                ->extraAttributes(['tabindex' => -1])
                                ->action(fn (Set $set) => $set('qualifier_name', 'N/A'))
                        )
                        ->dehydrateStateUsing(fn ($state) => $state === 'N/A' ? '' : $state)
                        ->markAsRequired()
                        ->rules('required'),
                    TextInput::make('prefix_name')
                        ->label('Name prefix')
                        ->helperText('Prefix or title of the employee e.g. Atty., Engr., Dr., Fr., etc.')
                        ->minLength(2)
                        ->markAsRequired(),
                    TextInput::make('suffix_name')
                        ->label('Name suffix')
                        ->helperText('Suffix or honorific of the employee e.g. Ph.D., M.D., MIT, MBA, MPA, etc.')
                        ->minLength(2)
                        ->markAsRequired(),
                    DatePicker::make('birthdate'),
                    Select::make('sex')
                        ->options(['male' => 'Male', 'female' => 'Female']),
                ]),
            Section::make('Employment Details')
                ->compact($compact)
                ->columns(6)
                ->schema([
                    TextInput::make('designation')
                        // ->requiredWith('status')
                        ->columnSpan(2),
                    Select::make('status')
                        ->options(EmploymentStatus::class)
                        ->afterStateUpdated(fn (callable $set) => $set('substatus', ''))
                        ->dehydrateStateUsing(fn ($state) => empty(trim($state instanceof EmploymentStatus ? $state->value : $state)) ? '' : $state)
                        ->disableOptionWhen(fn (string $value) => $value === EmploymentStatus::NONE)
                        ->columns(3)
                        ->searchable()
                        ->columnSpan(2)
                        ->live(),
                    Select::make('substatus')
                        ->options(EmploymentSubstatus::class)
                        ->requiredIf('status', EmploymentStatus::CONTRACTUAL)
                        ->prohibitedUnless('status', EmploymentStatus::CONTRACTUAL)
                        ->disableOptionWhen(fn (string $value) => $value === EmploymentSubstatus::NONE)
                        ->hidden(fn (Get $get) => $get('status') !== EmploymentStatus::CONTRACTUAL)
                        ->dehydrateStateUsing(fn ($state) => empty(trim($state instanceof EmploymentSubstatus ? $state->value : $state)) ? '' : $state)
                        ->dehydratedWhenHidden()
                        ->searchable()
                        ->columnSpan(2),
                ]),
            Section::make('Account Settings')
                ->visible($isCalledBySelf)
                ->columns(3)
                ->schema([
                    TextInput::make('uid')
                        ->visibleOn('view'),
                    TextInput::make('uid')
                        ->label('UID')
                        ->helperText('This eight character UID will be used to uniquely identify the employee across interconnected systems.')
                        ->minLength(8)
                        ->maxLength(8)
                        ->alphaNum()
                        ->markAsRequired()
                        ->rule('required')
                        ->rule('regex:/^(?!abcde123$)[A-Za-z]{5}\d{3}$/')
                        ->length(8)
                        ->validationAttribute('uid')
                        ->dehydrateStateUsing(fn ($state) => strtolower($state))
                        ->unique(ignoreRecord: true)
                        ->hiddenOn('view')
                        ->validationMessages([
                            'regex' => 'The :attribute must be in the format of five letters followed by three numbers e.g. abcde123 (except abcde123).',
                        ])
                        ->hintAction(
                            Action::make('generate')
                                ->label('Generate')
                                ->icon('heroicon-o-arrow-path')
                                ->action(function (Set $set) {
                                    $valid = function (string $uid): bool {
                                        return Employee::whereUid($uid)->doesntExist();
                                    };

                                    $set('uid', strtolower(fake()->valid($valid)->bothify('?????###')));
                                })
                        ),
                    TextInput::make('email')
                        ->rule('email:rfc,strict,dns,spoof,filter')
                        ->rule('required', fn (?Employee $record) => isset($record) && ! empty($record->email))
                        ->markAsRequired(fn (?Employee $record) => isset($record) && ! empty($record->email))
                        ->helperText('The email address will be used for account recovery, notifications, and other communication purposes.'),
                    ToggleButtons::make('active')
                        ->boolean()
                        ->inline()
                        ->grouped()
                        ->required()
                        ->default(true),
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
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('offices.code')
                    ->formatStateUsing(function (Employee $record) {
                        $offices = $record->offices->map(function ($office) {
                            return str($office->code)
                                ->when($office->pivot->current, function ($code) {
                                    return <<<HTML
                                        <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--primary-400);--c-600:var(--primary-600);">$code</span>
                                    HTML;
                                });
                        })->join(', ');

                        return str($offices)->toHtmlString();
                    }),
                TextColumn::make('status')
                    ->toggleable()
                    ->getStateUsing(function (Employee $employee): string {
                        return str($employee->status?->value)
                            ->title()
                            ->when($employee->substatus?->value, function ($status) use ($employee) {
                                return $status->append(" ({$employee->substatus->value})")->replace('_', '-')->title();
                            });
                    }),
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
                TernaryFilter::make('undeployed')
                    ->attribute('office_id')
                    ->trueLabel('No')
                    ->falseLabel('Yes')
                    ->nullable()
                    ->native(false)
                    ->queries(
                        fn ($query) => $query->whereHas('offices'),
                        fn ($query) => $query->whereDoesntHave('offices'),
                    ),
                OfficeFilter::make(),
                StatusFilter::make(),
                SelectFilter::make('groups')
                    ->multiple()
                    ->searchable()
                    ->relationship('groups', 'name')
                    ->preload(),
                ActiveFilter::make(),
                TrashedFilter::make()
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
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
            OfficesRelationManager::class,
            ScannersRelationManager::class,
            GroupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                ActiveScope::class,
                SoftDeletingScope::class,
                'excludeInterns',
            ]);
    }
}
