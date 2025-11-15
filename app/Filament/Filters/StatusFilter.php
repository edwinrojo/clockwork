<?php

namespace App\Filament\Filters;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentSubstatus;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

class StatusFilter extends Filter
{
    protected Closure|string|null $relationship = null;

    protected bool $single = false;

    public static function make(?string $name = null): static
    {
        $filterClass = static::class;

        $name ??= 'status-filter';

        $static = app($filterClass, ['name' => $name]);

        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema([
            Select::make('status')
                ->options(EmploymentStatus::class)
                ->placeholder('All')
                ->multiple(fn () => ! $this->single)
                ->searchable(),
            Select::make('substatus')
                ->visible(function (callable $get) {
                    if ($this->single) {
                        if (is_array($get('status'))) {
                            return;
                        }

                        return $get('status') === EmploymentStatus::CONTRACTUAL->value;
                    }

                    $visibleOn = [
                        EmploymentStatus::CONTRACTUAL->value,
                    ];

                    return count(array_diff($visibleOn, $get('status') ?? [])) < count($visibleOn);
                })
                ->options(EmploymentSubstatus::class)
                ->placeholder('All')
                ->multiple(fn () => ! $this->single)
                ->searchable(),
        ]);

        $this->query(function (Builder $query, array $data) {
            if (! isset($data['status'])) {
                return;
            }

            $filter = function (Builder $query) use ($data) {
                if ($this->single) {
                    if (is_array($data['status'])) {
                        return;
                    }

                    $query->when(
                        EmploymentStatus::INTERNSHIP->value === $data['status'],
                        fn ($query) => $query->withoutGlobalScope('excludeInterns'),
                    );

                    $query->where('status', $data['status']);

                    if (is_array($data['substatus'])) {
                        return;
                    }

                    $query->when(
                        $data['substatus'],
                        fn ($query) => $query->where('substatus', $data['substatus'])
                    );

                    return;
                }

                if (is_string($data['status'])) {
                    return;
                }

                $query->when(
                    in_array(EmploymentStatus::INTERNSHIP->value, $data['status']),
                    fn ($query) => $query->withoutGlobalScope('excludeInterns'),
                );

                $query->when(
                    $data['status'],
                    fn ($query) => $query->whereIn('status', $data['status'])
                );

                if (is_string($data['substatus'])) {
                    return;
                }

                $query->when(
                    $data['substatus'],
                    fn ($query) => $query->whereIn('substatus', $data['substatus'])
                );
            };

            $query->when(
                $this->relationship instanceof Closure ? ($this->relationship)() : $this->relationship,
                fn ($query, $relationship) => $query->whereHas($relationship, $filter),
                $filter
            );
        });

        $this->indicateUsing(function (array $data) {
            $indicators = [];

            if ($this->single) {
                if ($hasStatus = isset($data['status']) && is_string($data['status']) && strlen($data['status'])) {
                    $indicators[] = Indicator::make('Status: '.EmploymentStatus::tryFrom($data['status'])?->getLabel())->removeField('status');
                }

                if ($hasStatus && isset($data['substatus']) && is_string($data['substatus']) && strlen($data['substatus'])) {
                    $indicators[] = Indicator::make('Substatus: '.EmploymentSubstatus::tryFrom($data['substatus'])?->getLabel())->removeField('substatus');
                }

                return count($indicators) ? $indicators : null;
            }

            if ($hasStatus = isset($data['status']) && is_array($data['status']) && count($data['status'])) {
                $statuses = collect($data['status'])
                    ->map(fn ($status) => EmploymentStatus::tryFrom($status)?->getLabel());

                $indicators[] = Indicator::make('Status: '.$statuses->join(', '))->removeField('status');
            }

            if ($hasStatus && isset($data['substatus']) && is_array($data['substatus']) && count($data['substatus'])) {
                $substatuses = collect($data['substatus'])
                    ->map(fn ($status) => EmploymentSubstatus::tryFrom($status)->getLabel());

                $indicators[] = Indicator::make('Substatus: '.$substatuses->join(', '))->removeField('substatus');
            }

            return count($indicators) ? $indicators : null;
        });
    }

    public function relationship(Closure|string|null $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function single(bool $single = true): static
    {
        $this->single = $single;

        return $this;
    }
}
