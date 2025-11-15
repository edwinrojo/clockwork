<?php

namespace App\Filament\Superuser\Resources\GroupResource\RelationManagers;

use App\Filament\Filters\ActiveFilter;
use App\Models\Employee;
use App\Models\Member;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Employee members';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->relationship('employee', 'name', function ($query) {
                        $admin = Filament::getCurrentOrDefaultPanel()->getId() === 'superuser';

                        if ($admin) {
                            return;
                        }

                        $query->where(function (Builder $query) {
                            $query->orWhereHas('offices', function (Builder $query) {
                                $query->whereIn('offices.id', user()->offices->pluck('id'));
                            });

                            $query->orWhereHas('scanners', function (Builder $query) {
                                $query->whereIn('scanners.id', user()->scanners->pluck('id'));
                            });
                        });
                    })
                    ->searchable()
                    ->preload()
                    ->disabledOn('edit')
                    ->required()
                    ->columnSpanFull()
                    ->validationMessages(['unique' => 'Employee is already a member of this group.'])
                    ->rules([
                        fn (?Member $record) => Rule::unique('member', 'employee_id')
                            ->where('group_id', $this->ownerRecord->id)
                            ->ignore($record?->id, 'id'),
                    ]),
                ToggleButtons::make('active')
                    ->boolean()
                    ->inline()
                    ->grouped()
                    ->required()
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['employee.offices', 'employee.scanners']);

                if (Filament::getCurrentOrDefaultPanel()->getId() === 'secretary') {
                    $query->whereHas('employee', function ($query) {
                        $query->whereHas('scanners', function ($query) {
                            $query->whereIn('scanners.id', user()->scanners->pluck('id'));
                        });

                        $query->orWhereHas('offices', function ($query) {
                            $query->whereIn('offices.id', user()->offices->pluck('id'));
                        });

                        $query->where('employees.active', true);
                    });
                } else {
                    $query->whereHas('employee');
                }
            })
            ->columns([
                TextColumn::make('employee.name')
                    ->searchable(),
                TextColumn::make('employee.offices.code')
                    ->searchable()
                    ->formatStateUsing(function (Member $record) {
                        $offices = $record->employee->offices->map(function ($office) {
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
                    ->getStateUsing(function (Member $record): string {
                        return str($record->employee->status?->value)
                            ->title()
                            ->when($record->employee->substatus?->value, function ($status) use ($record) {
                                return $status->append(" ({$record->employee->substatus->value})")->replace('_', '-')->title();
                            });
                    }),
                TextColumn::make('active')
                    ->getStateUsing(fn ($record) => $record->active ? 'Yes' : 'No')
                    ->icon(fn ($record) => $record->active ? 'heroicon-o-check' : 'heroicon-o-no-symbol')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ActiveFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->slideOver()
                    ->modalWidth('xl'),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('xl')
                    ->visible(function (Member $record) {
                        $admin = Filament::getCurrentOrDefaultPanel()->getId() === 'superuser';

                        if ($admin) {
                            return true;
                        }

                        $user = user();

                        $offices = $user->offices->map(function ($office) {
                            return $office->id;
                        });

                        $scanners = $user->scanners->map(function ($scanner) {
                            return $scanner->id;
                        });

                        return $record->employee?->offices->some(fn ($office) => in_array($office->id, $offices->toArray())) ||
                            $record->employee?->scanners->some(fn ($scanner) => in_array($scanner->id, $scanners->toArray()));
                    }),
                DeleteAction::make()
                    ->icon('heroicon-o-x-circle')
                    ->modalIcon('heroicon-o-shield-exclamation')
                    ->visible(function (Member $record) {
                        $admin = Filament::getCurrentOrDefaultPanel()->getId() === 'superuser';

                        if ($admin) {
                            return true;
                        }

                        $user = user();

                        $offices = $user->offices->map(function ($office) {
                            return $office->id;
                        });

                        $scanners = $user->scanners->map(function ($scanner) {
                            return $scanner->id;
                        });

                        return $record->employee?->offices->some(fn ($office) => in_array($office->id, $offices->toArray())) ||
                            $record->employee?->scanners->some(fn ($scanner) => in_array($scanner->id, $scanners->toArray()));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->icon('heroicon-o-x-circle')
                        ->modalIcon('heroicon-o-shield-exclamation'),
                ]),
            ])
            ->defaultSort(function (Builder $query) {
                $query->orderBy(
                    Employee::select('status')
                        ->whereColumn('employees.id', 'member.employee_id')
                        ->limit(1),
                    'desc'
                );

                $query->orderBy(
                    Employee::select('substatus')
                        ->whereColumn('employees.id', 'member.employee_id')
                        ->limit(1),
                );

                $query->orderBy(
                    Employee::select('name')
                        ->whereColumn('employees.id', 'member.employee_id')
                        ->limit(1),
                );
            })
            ->recordAction(null);
    }
}
