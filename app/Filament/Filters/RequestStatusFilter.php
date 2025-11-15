<?php

namespace App\Filament\Filters;

use App\Enums\RequestStatus;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

class RequestStatusFilter extends BaseFilter
{
    protected string $relationship = 'request';

    protected string $field = 'status';

    public static function make(?string $name = null): static
    {
        $filterClass = static::class;

        $name ??= 'request-status-filter';

        $static = app($filterClass, ['name' => $name]);

        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->relationship('request', 'status');

        $this->schema([
            Select::make('status')
                ->options(RequestStatus::class)
                ->multiple()
                ->searchable(),
        ]);

        $this->query(function (Builder $query, array $data) {
            $query->when($data['status'], function (Builder $query, array $status) {
                $query->whereHas($this->relationship, fn (Builder $query) => $query->whereIn($this->field, $status));
            });
        });

        $this->indicateUsing(function (array $state) {
            if (blank($state['status'] ?? null)) {
                return [];
            }

            $labels = collect(RequestStatus::cases())
                ->mapWithKeys(fn (RequestStatus $status): array => [$status->value => $status->getLabel()])
                ->only($state['status']);

            if ($labels->isEmpty()) {
                return [];
            }

            return [Indicator::make('Status: '.$labels->join(', ', ' & '))];
        });
    }

    public function relationship(string $relationship = 'request', string $field = 'status'): static
    {
        $this->relationship = $relationship;

        $this->field = $field;

        return $this;
    }
}
