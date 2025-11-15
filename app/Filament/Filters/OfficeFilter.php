<?php

namespace App\Filament\Filters;

use App\Models\Office;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class OfficeFilter extends Filter
{
    protected ?string $relationship = null;

    public static function make(?string $name = null): static
    {
        $filterClass = static::class;

        $name ??= 'office-filter';

        $static = app($filterClass, ['name' => $name]);

        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema([
            Select::make('offices')
                ->options(
                    Office::query()
                        ->when(Filament::getCurrentOrDefaultPanel()->getId() === 'secretary', function (Builder $query) {
                            $query->where(function ($query) {
                                $user = user();

                                $query->whereIn('id', $user->offices->pluck('id'));

                                $query->orWhereHas('employees', function ($query) use ($user) {
                                    $query->whereHas('scanners', function ($query) use ($user) {
                                        $query->whereIn('scanners.id', $user->scanners->pluck('id')->toArray());

                                        $query->where('enrollment.active', true);
                                    });

                                    $query->where('deployment.active', true);
                                });
                            });
                        })
                        ->orderBy('code')
                        ->pluck('code', 'id')
                )
                ->placeholder('All')
                ->searchable()
                ->getSearchResultsUsing(function (string $search) {
                    $user = user();

                    $query = Office::query();

                    $query->when(Filament::getCurrentOrDefaultPanel()->getId() === 'secretary', function ($query) use ($user) {
                        $query->where(function ($query) use ($user) {
                            $query->whereIn('id', $user->offices()->select('offices.id'));

                            $query->orWhereHas('employees', function ($query) use ($user) {
                                $query->whereHas('scanners', function (Builder $query) use ($user) {
                                    $query->whereIn('scanners.id', $user->scanners()->select('scanners.id'));
                                });
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
        ]);

        $this->query(function (Builder $query, array $data) {
            $filter = fn (Builder $query) => $query->when($data['offices'], function ($query) use ($data) {
                $query->whereHas('offices', function ($query) use ($data) {
                    $query->whereIn('offices.id', $data['offices'])
                        ->where('deployment.active', true);
                });
            });

            $query->when($this->relationship, fn ($query) => $query->whereHas($this->relationship, $filter), $filter);
        });

        $this->indicateUsing(function (array $data) {
            if (empty($data['offices'])) {
                return null;
            }

            $offices = Office::select('code')
                ->orderBy('code')
                ->find($data['offices'])
                ->pluck('code');

            return 'Offices: '.$offices->join(', ');
        });
    }

    public function relationship(?string $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }
}
