<?php

namespace App\Filament\Developer\Resources;

use App\Filament\Developer\Resources\TokenResource\Pages\ListTokens;
use App\Models\Token;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TokenResource extends Resource
{
    protected static ?string $model = Token::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-token-o';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Year'),
                TextColumn::make('last_used_at')
                    ->since(),
                TextColumn::make('created_at')
                    ->since(),
            ])
            ->filters([
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListTokens::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $query->where('tokenable_type', User::class)
            ->where('tokenable_id', auth()->id());

        return $query;
    }
}
