<?php

namespace App\Filament\Employee\Resources;

use App\Enums\TimelogMode;
use App\Enums\TimelogState;
use App\Filament\Employee\Resources\TimelogResource\Pages\ListTimelogs;
use App\Models\Timelog;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TimelogResource extends Resource
{
    protected static ?string $model = Timelog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-alarm-on-o';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $base = (clone $query)
                    ->select([
                        'timelogs.*',
                        DB::raw('ROW_NUMBER() OVER (PARTITION BY DATE(time) ORDER BY time ASC) AS limitation'),
                    ])
                    ->whereHas('employee', fn ($q) => $q->whereKey(Auth::id()));
            
                $query->fromSub($base, 'timelogs')
                    ->withoutGlobalScopes()
                    ->select('timelogs.*')
                    ->where('timelogs.limitation', '<=', 10)
                    ->with('original');
            })
            ->columns([
                TextColumn::make('scanner.name')
                    ->searchable()
                    ->sortable()
                    ->extraAttributes(['class' => 'font-mono']),
                TextColumn::make('time')
                    ->searchable()
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->extraAttributes(['class' => 'font-mono']),
                TextColumn::make('uid')
                    ->label('UID')
                    ->searchable(query: fn ($query, $search) => $query->whereUid($search)),
                TextColumn::make('state'),
                TextColumn::make('mode'),
                TextColumn::make('recast')
                    ->alignEnd()
                    ->label('Rectified')
                    ->badge()
                    ->tooltip(fn (Timelog $record) => $record->recast ? $record->original->state->getLabel() : null)
                    ->icon(fn (Timelog $record) => $record->recast ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-shield-check')
                    ->color(fn (Timelog $record) => $record->recast ? 'warning' : 'primary')
                    ->state(fn (Timelog $record) => $record->recast ? 'Yes' : 'No'),
            ])
            ->filters([
                TernaryFilter::make('recast')
                    ->label('Rectified')
                    ->native(false)
                    ->queries(
                        true: fn ($query) => $query->where('recast', true),
                        false: fn ($query) => $query->where('recast', false),
                    ),
                Filter::make('time')
                    ->schema([
                        DateTimePicker::make('from')
                            ->seconds(false),
                        DateTimePicker::make('until')
                            ->seconds(false),
                    ])
                    ->query(function ($query, array $data) {
                        $query->when($data['from'], fn ($q, $d) => $q->where('time', '>=', Carbon::parse($d)->format('Y-m-d H:i:s')));
                        $query->when($data['until'], fn ($q, $d) => $q->where('time', '<=', Carbon::parse($d)->format('Y-m-d H:i:s')));
                    })
                    ->indicateUsing(function (array $data) {
                        $indicators = [];

                        if (isset($data['from'])) {
                            $indicators[] = Indicator::make('From: '.Carbon::parse($data['from'])->format('Y-m-d H:i'))
                                ->removeField('from');
                        }

                        if (isset($data['until'])) {
                            $indicators[] = Indicator::make('Until: '.Carbon::parse($data['until'])->format('Y-m-d H:i'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('scanner')
                    ->relationship('scanner', 'name', fn ($query) => $query->whereHas('employees', fn ($query) => $query->where('employees.id', Auth::id()))->reorder()->orderBy('priority', 'desc')->orderBy('name'))
                    ->searchable()
                    ->multiple()
                    ->preload(),
                SelectFilter::make('mode')
                    ->options(TimelogMode::class)
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('state')
                    ->options(TimelogState::class)
                    ->multiple()
                    ->searchable(),
            ])
            ->deferLoading()
            ->defaultSort('time', 'desc')
            ->recordAction(null)
            ->recordUrl(null);
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
            'index' => ListTimelogs::route('/'),
        ];
    }
}
