<?php

namespace App\Filament\Superuser\Resources;

use App\Enums\HolidayType;
use App\Filament\Superuser\Resources\HolidayResource\Pages\ListSuspensions;
use App\Models\Holiday;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-free-cancellation-o';

    public static function form(Schema $schema): Schema
    {
        return $form
            ->schema([
                Select::make('type')
                    ->live()
                    ->columnSpanFull()
                    ->rule('required')
                    ->markAsRequired()
                    ->options(HolidayType::class)
                    ->default(HolidayType::REGULAR_HOLIDAY),
                DatePicker::make('date')
                    ->live()
                    ->columnSpanFull()
                    ->markAsRequired()
                    ->rule('required'),
                TimePicker::make('from')
                    ->columnSpanFull()
                    ->seconds(false)
                    ->visible(fn (Get $get) => $get('type') === HolidayType::WORK_SUSPENSION || $get('type') === HolidayType::WORK_SUSPENSION->value),
                TextInput::make('name')
                    ->columnSpanFull()
                    ->rule('required')
                    ->markAsRequired()
                    ->maxLength(255),
                Textarea::make('remarks')
                    ->columnSpanFull()
                    ->maxLength(255)
                    ->rows(5),
                TextInput::make('password')
                    ->columnSpanFull()
                    ->password()
                    ->currentPassword()
                    ->rule('required')
                    ->markAsRequired()
                    ->visible(function (?Holiday $record, Get $get) {
                        if ($record?->date->lt(now())) {
                            return true;
                        }

                        if (is_null($get('date'))) {
                            return false;
                        }

                        return Carbon::parse($get('date'))->lt(now());
                    }),
                Hidden::make('created_by')
                    ->default(Auth::id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->formatStateUsing(fn (?string $state) => Carbon::parse($state)->format('jS F Y'))
                    ->sortable(),
                TextColumn::make('type'),
                TextColumn::make('createdBy.name')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
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
                EditAction::make()
                    ->slideOver()
                    ->requiresConfirmation()
                    ->modalDescription('Modifying past holidays or suspensions (from or to) will require you to enter your password as this may have an irreversible side-effect.')
                    ->modalWidth('xl'),
                DeleteAction::make()
                    ->modalDescription(function (?Holiday $record) {
                        $needsPassword = now()->isAfter($record->date);

                        $confirmation = 'This date has already passed. This action will have an irreversible effect. <br>';

                        return str(($needsPassword ? $confirmation : '').'Are you sure you would like to do this?')
                            ->toHtmlString();
                    })
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->currentPassword()
                            ->rules(['required'])
                            ->visible(fn (Holiday $record) => $record->recurring || now()->isAfter($record->date)),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->deferLoading()
            ->recordAction(null)
            ->defaultSort('date', 'desc');
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
            'index' => ListSuspensions::route('/'),
        ];
    }
}
