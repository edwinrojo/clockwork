<?php

namespace App\Filament\Secretary\Resources;

use App\Filament\Filters\ActiveFilter;
use App\Filament\Filters\StatusFilter;
use App\Filament\Secretary\Resources\EmployeeResource\Pages\EditEmployee;
use App\Filament\Secretary\Resources\EmployeeResource\Pages\ListEmployees;
use App\Filament\Superuser\Resources\EmployeeResource as SuperuserEmployeeResource;
use App\Filament\Superuser\Resources\EmployeeResource\RelationManagers\GroupsRelationManager;
use App\Filament\Superuser\Resources\EmployeeResource\RelationManagers\OfficesRelationManager;
use App\Filament\Superuser\Resources\EmployeeResource\RelationManagers\ScannersRelationManager;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Scopes\ActiveScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'gmdi-badge-o';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return $form
            ->schema(SuperuserEmployeeResource::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('offices.code')
                    ->formatStateUsing(function (Employee $record) {
                        $offices = $record->offices->map(function ($office) {
                            return str($office->code)
                                ->when($office->pivot->current, function ($code) {
                                    return <<<HTML
                                        <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--primary-400);--c-600:var(--primary-600);">$code</span>
                                    HTML;
                                });
                        })->join(', ');

                        return str($offices)->toHtmlString();
                    }),
                TextColumn::make('status'),
            ])
            ->filters([
                StatusFilter::make(),
                Filter::make('offices')
                    ->schema([
                        Select::make('offices')
                            ->options(
                                Office::query()
                                    ->where(function ($query) {
                                        $query->whereIn('id', Auth::user()->offices->pluck('id'));

                                        $query->orWhereHas('employees', function ($query) {
                                            $query->whereHas('scanners', function (Builder $query) {
                                                $query->whereIn('scanners.id', Auth::user()->scanners->pluck('id')->toArray());
                                            });
                                        });
                                    })
                                    ->pluck('code', 'id')
                            )
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $query = Office::query();

                                $query->where(function ($query) {
                                    $query->whereIn('id', Auth::user()->offices->pluck('id'));

                                    $query->orWhereHas('employees', function ($query) {
                                        $query->whereHas('scanners', function (Builder $query) {
                                            $query->whereIn('scanners.id', Auth::user()->scanners->pluck('id')->toArray());
                                        });
                                    });
                                });

                                $query->where(function ($query) use ($search) {
                                    $query->where('code', 'ilike', "%{$search}%")
                                        ->orWhere('name', 'ilike', "%{$search}%");
                                });

                                return $query->pluck('code', 'id');
                            })
                            ->preload()
                            ->multiple(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when($data['offices'], function ($query) use ($data) {
                            $query->whereHas('offices', function ($query) use ($data) {
                                $query->whereIn('offices.id', $data['offices']);
                                $query->where('deployment.active', true);
                            });

                        });
                    })
                    ->indicateUsing(function (array $data) {
                        if (empty($data['offices'])) {
                            return null;
                        }

                        $offices = Office::select('code')
                            ->orderBy('code')
                            ->find($data['offices'])
                            ->pluck('code');

                        return 'Offices: '.$offices->join(', ');
                    }),
                ActiveFilter::make(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
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
            OfficesRelationManager::class,
            ScannersRelationManager::class,
            GroupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                ActiveScope::class,
                SoftDeletingScope::class,
                'excludeInterns',
            ])
            ->where(function (Builder $query) {
                $query->orWhereHas('offices', function (Builder $query) {
                    $query->whereIn('offices.id', user()->offices()->pluck('offices.id'));
                });

                $query->orWhereHas('scanners', function (Builder $query) {
                    $query->whereIn('scanners.id', user()->scanners()->pluck('scanners.id'));
                });
            });
    }
}
