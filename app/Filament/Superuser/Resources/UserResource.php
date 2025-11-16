<?php

namespace App\Filament\Superuser\Resources;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Filament\Superuser\Resources\UserResource\Pages\CreateUser;
use App\Filament\Superuser\Resources\UserResource\Pages\EditUser;
use App\Filament\Superuser\Resources\UserResource\Pages\ListUsers;
use App\Filament\Superuser\Resources\UserResource\RelationManagers\OfficesRelationManager;
use App\Filament\Superuser\Resources\UserResource\RelationManagers\ScannersRelationManager;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-supervisor-account-o';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Information and Credentials')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->columnSpan(2)
                            ->rule('required')
                            ->markAsRequired()
                            ->maxLength(255),
                        TextInput::make('position')
                            ->columnSpan(2)
                            ->maxLength(255),
                        TextInput::make('username')
                            ->columnSpan(2)
                            ->rule('required')
                            ->markAsRequired()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->columnSpan(2)
                            ->rule('required')
                            ->rule('email')
                            ->unique(ignoreRecord: true)
                            ->markAsRequired()
                            ->maxLength(255),
                        TextInput::make('password')
                            ->columnSpan(2)
                            ->password()
                            ->rule(Password::default())
                            ->rule(fn (string $operation) => $operation === 'create' ? 'required' : null)
                            ->markAsRequired(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => ! empty($state))
                            ->requiredWith('passwordConfirmation')
                            ->same('passwordConfirmation')
                            ->hiddenOn(['view', 'edit']),
                        TextInput::make('passwordConfirmation')
                            ->columnSpan(2)
                            ->password()
                            ->rule(fn (string $operation) => $operation === 'create' ? 'required' : null)
                            ->markAsRequired(fn (string $operation) => $operation === 'create')
                            ->requiredWith('password')
                            ->dehydrated(false)
                            ->hiddenOn(['view', 'edit']),
                    ]),
                Section::make('Employee Account Link')
                    ->columns(3)
                    ->schema([
                        Select::make('employee_id')
                            ->columnSpan(2)
                            ->relationship('employee', 'full_name')
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make('Roles and Permissions')
                    ->columns(3)
                    ->schema([
                        Group::make([
                            CheckboxList::make('roles')
                                ->live()
                                ->bulkToggleable()
                                ->options(function () {
                                    return collect(array_combine(array_column(UserRole::cases(), 'name'), array_column(UserRole::cases(), 'value')))
                                        ->filter(fn ($value) => Auth::user()->root ? true : ! in_array($value, [UserRole::ROOT->value, UserRole::DEVELOPER->value]))
                                        ->mapWithKeys(fn ($value, $name) => [$value => UserRole::tryFrom($value)->getLabel()])
                                        ->toArray();
                                }),
                        ])->columnSpan(1),
                        Group::make([
                            CheckboxList::make('permissions')
                                ->visible(fn (Get $get) => in_array(UserRole::SUPERUSER->value, $get('roles')))
                                ->dehydratedWhenHidden()
                                ->dehydrateStateUsing(fn (Get $get, array $state) => in_array(UserRole::SUPERUSER->value, $get('roles')) ? $state : [])
                                ->hint('Select resources that the superuser can access.')
                                ->bulkToggleable()
                                ->columns(2)
                                ->options(UserPermission::class),
                        ])->columnSpan(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('username')
                    ->searchable(),
                TextColumn::make('roles')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('offices.code')
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
                SelectFilter::make('offices')
                    ->relationship('offices', 'code')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->native(false),
                TrashedFilter::make()
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->deferLoading()
            ->recordUrl(null);
    }

    public static function getRelations(): array
    {
        return [
            ScannersRelationManager::class,
            OfficesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // ->whereNot('id', auth()->id())
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
