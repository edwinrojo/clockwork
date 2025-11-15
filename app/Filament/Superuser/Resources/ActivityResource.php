<?php

namespace App\Filament\Superuser\Resources;

use App\Filament\Superuser\Resources\ActivityResource\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Scanner;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-monitor-heart-o';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('activitable_type')
                    ->label('Resource')
                    ->getStateUsing(fn (Activity $record) => $record->activitable ? class_basename($record->activitable::class) : '')
                    ->searchable(),
                TextColumn::make('activitable.name')
                    ->label('Name')
                    ->getStateUsing(fn (Activity $record) => $record->activitable ? $record->activitable->name : '???')
                    ->searchable(),
                TextColumn::make('data.action')
                    ->label('Action')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('activitable_type')
                    ->options([
                        Scanner::class => 'Scanner',
                        User::class => 'User',
                    ])
                    ->label('Resource')
                    ->searchable(),
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        KeyValueEntry::make('data')
                            ->hiddenLabel()
                            ->keyLabel('Identifier')
                            ->valueLabel('Data')
                            ->getStateUsing(function (Activity $record) {
                                return [
                                    'time' => $record->time,
                                    'type' => @class_basename($record->activitable_type) ?? 'Data import',
                                    'name' => $record->activitable?->name,
                                    'user' => "{$record->user->name} ({$record->user->email})",
                                    ...$record->data,
                                ];
                            }),
                    ]),
            ])
            ->deferLoading()
            ->defaultSort('created_at', 'desc');
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
            'index' => ListActivities::route('/'),
        ];
    }
}
