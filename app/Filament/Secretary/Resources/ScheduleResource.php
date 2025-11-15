<?php

namespace App\Filament\Secretary\Resources;

use App\Enums\RequestStatus;
use App\Filament\Actions\Request\TableActions\CancelAction;
use App\Filament\Actions\Request\TableActions\ShowRoutingAction;
use App\Filament\Filters\RequestStatusFilter;
use App\Filament\Secretary\Resources\ScheduleResource\Pages;
use App\Filament\Secretary\Resources\ScheduleResource\Pages\CreateSchedule;
use App\Filament\Secretary\Resources\ScheduleResource\Pages\EditSchedule;
use App\Filament\Secretary\Resources\ScheduleResource\Pages\ListSchedules;
use App\Filament\Secretary\Resources\ScheduleResource\Pages\ViewSchedule;
use App\Filament\Secretary\Resources\ScheduleResource\RelationManagers\EmployeesRelationManager;
use App\Filament\Superuser\Resources\ScheduleResource as SuperuserScheduleResource;
use App\Models\Schedule;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-punch-clock-o';

    public static function form(Schema $schema): Schema
    {
        return SuperuserScheduleResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->visible(settings('requests'))
                    ->searchable()
                    ->getStateUsing(fn (Schedule $record) => $record->drafted ? null : ($record->request->cancelled ? null : $record->title))
                    ->placeholder(fn (Schedule $record) => $record->drafted ? 'Drafted' : ($record->request->cancelled ? 'Cancelled' : $record->title)),
                TextColumn::make('period')
                    ->extraCellAttributes(['class' => 'font-mono']),
                TextColumn::make('days')
                    ->label('Days')
                    ->formatStateUsing(function (Schedule $record): string {
                        return match ($record->days) {
                            'everyday' => 'Everyday',
                            'weekday' => 'Weekdays',
                            // 'holiday' => 'Holiday',
                            'weekend' => 'Weekends',
                        };
                    }),
                TextColumn::make('request.status')
                    ->visible(settings('requests'))
                    ->placeholder('Draft'),
                TextColumn::make('request.user.name')
                    ->label('Requestor'),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                RequestStatusFilter::make(),
                TrashedFilter::make()
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn (?Schedule $record) => ! in_array($record?->request?->status, [null, RequestStatus::CANCEL, RequestStatus::RETURN])),
                EditAction::make()
                    ->hidden(fn (?Schedule $record) => ! in_array($record?->request?->status, [null, RequestStatus::CANCEL, RequestStatus::RETURN])),
                ActionGroup::make([
                    ShowRoutingAction::make(),
                    CancelAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->recordUrl(null)
            ->deferLoading();
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
            'index' => ListSchedules::route('/'),
            'create' => CreateSchedule::route('/create'),
            // 'create-overtime' => Pages\CreateOvertimeSchedule::route('/create-overtime'),
            'view' => ViewSchedule::route('/{record}/view'),
            'edit' => EditSchedule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $offices = user()->offices?->pluck('id')->toArray();

        return parent::getEloquentQuery()
            ->whereNot('global', true)
            ->when(count($offices), fn ($q) => $q->whereIn('office_id', empty($offices) ? [] : $offices))
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
