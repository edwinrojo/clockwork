<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeploymentFilterService
{
    public function applyDeploymentActiveFilter(Builder|BelongsToMany $query, bool $active = true, bool $inactive = false): void
    {
        match (true) {
            $active && $inactive => $query->wherePivotIn('active', [true, false]),
            ! $active && ! $inactive => $query->whereRaw('1 = 0'),
            $inactive => $query->wherePivotIn('active', [false]),
            default => null,
        };
    }
}
