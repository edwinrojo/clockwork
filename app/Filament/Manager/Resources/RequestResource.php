<?php

namespace App\Filament\Manager\Resources;

use App\Enums\RequestStatus;
use App\Filament\Actions\Request\TableActions\RespondAction;
use App\Filament\Manager\Resources\RequestResource\Pages\ListRequests;
use App\Models\Request;
use App\Models\Schedule;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-rule-folder-o';

    public static function form(Schema $schema): Schema
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requestable')
                    ->label('Type')
                    ->getStateUsing(fn ($record) => class_basename($record->requestable)),
                TextColumn::make('requestable.title')
                    ->label('Title')
                    ->searchable()
                    ->getStateUsing(fn (Request $record) => $record->requestable->title),
                TextColumn::make('status'),
                TextColumn::make('requestable.requestor.name')
                    ->placeholder('N/A'),
                TextColumn::make('status'),
                TextColumn::make('to')
                    ->label('Target')
                    ->getStateUsing(fn (Request $record) => ucfirst($record->to))
                    ->placeholder('N/A'),
                TextColumn::make('requestable.requested_at')
                    ->label('Time')
                    ->placeholder(fn ($record) => $record->created_at),
            ])
            ->filters([
                SelectFilter::make('requestable_type')
                    ->label('Type')
                    ->native(false)
                    ->options([
                        Schedule::class => 'Schedule',
                    ]),
                SelectFilter::make('status')
                    ->options(RequestStatus::class)
                    ->default([RequestStatus::REQUEST->value, RequestStatus::DEFLECT->value])
                    ->multiple()
                    ->native(false),
                // Tables\Filters\SelectFilter::make('for')
                //     ->options(['approval' => 'Approval', 'revokation' => 'Revokation', 'termination' => 'Termination'])
                //     ->multiple()
                //     ->native(false),
                SelectFilter::make('completed')
                    ->options([true => 'Completed', false => 'Pending'])
                    ->native(false),
            ])
            ->recordActions([
                Action::make('view')
                    ->modalHeading(fn (Request $record) => $record->requestable->title)
                    ->modalContent(fn (Request $record) => view('filament.requests.view', ['schedule' => $record->requestable, 'request' => $record]))
                    ->modalCancelActionLabel('Close')
                    ->modalSubmitAction(false)
                    ->modalWidth('2xl')
                    ->slideOver(),
                RespondAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([

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
            'index' => ListRequests::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->whereNot('status', RequestStatus::CANCEL);

                    $query->whereIn('id', Request::selectRaw('MAX(requests.id)')->groupBy('requestable_id', 'requestable_type'));
                });

                $query->orWhere('completed', true);
            })
            ->whereHas('requestable');
    }
}
