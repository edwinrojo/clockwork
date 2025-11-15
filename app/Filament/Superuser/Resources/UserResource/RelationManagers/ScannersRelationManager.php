<?php

namespace App\Filament\Superuser\Resources\UserResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScannersRelationManager extends RelationManager
{
    protected static string $relationship = 'scanners';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->attachAnother(false)
                    ->preloadRecordSelect()
                    ->slideOver()
                    ->multiple()
                    ->label('Assign'),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remove'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove'),
                ]),
            ])
            ->inverseRelationship('users');
    }
}
