<?php

namespace App\Filament\Superuser\Resources;

use App\Enums\TimelogMode;
use App\Enums\TimelogState;
use App\Filament\Superuser\Resources\TimelogResource\Pages\ListTimelogs;
use App\Models\Timelog;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TimelogResource extends Resource
{
    protected static ?string $model = Timelog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-alarm-on-o';

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
                TextColumn::make('scanner.name')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->extraAttributes(['class' => 'font-mono']),
                TextColumn::make('employee.name')
                    ->placeholder('Unknown')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('uid')
                    ->label('UID')
                    ->searchable(query: fn ($query, $search) => $query->whereUid($search), isIndividual: true, isGlobal: false),
                TextColumn::make('time')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->extraAttributes(['class' => 'font-mono']),
                TextColumn::make('state'),
                TextColumn::make('mode'),
            ])
            ->filters([
                Filter::make('time')
                    ->columnSpanFull()
                    ->columns(2)
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

                        if (isset($data['from']) && ! empty($data['from'])) {
                            $indicators[] = Indicator::make('From: '.Carbon::parse($data['from'])->format('Y-m-d H:i'))
                                ->removeField('from');
                        }

                        if (isset($data['until']) && ! empty($data['until'])) {
                            $indicators[] = Indicator::make('Until: '.Carbon::parse($data['until'])->format('Y-m-d H:i'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('scanner')
                    ->relationship('scanner', 'name')
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
                TernaryFilter::make('unknown')
                    ->label('Unknown')
                    ->placeholder('All')
                    ->trueLabel('Records from unknown')
                    ->falseLabel('Records with enrollments')
                    ->queries(
                        fn ($query) => $query->whereDoesntHave('employee'),
                        fn ($query) => $query->whereHas('employee'),
                    )
                    ->native(false),
            ], FiltersLayout::AboveContent)
            ->recordActions([

            ])
            ->toolbarActions([
                BulkActionGroup::make([

                ]),
            ])
            ->deferLoading()
            ->defaultSort('time', 'desc')
            ->filtersFormWidth(Width::ThreeExtraLarge)
            ->filtersFormColumns(2);
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
