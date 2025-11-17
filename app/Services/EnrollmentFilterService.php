<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EnrollmentFilterService
{
    public function applyEnrollmentActiveFilter(Builder|BelongsToMany $query, bool $active = true, bool $inactive = false): void
    {
        match (true) {
            $active && $inactive => $query->wherePivotIn('active', [true, false]),
            ! $active && ! $inactive => $query->whereRaw('1 = 0'),
            $inactive => $query->wherePivotIn('active', [false]),
            default => null,
        };
    }

    public function applyScannerActiveFilter(Builder $query, bool $active = true, bool $inactive = false): void
    {
        match (true) {
            $active && $inactive => null,
            ! $active && ! $inactive => $query->whereRaw('1 = 0'),
            $inactive => $query->where('scanners.active', false),
            default => $query->where('scanners.active', true),
        };
    }
}
