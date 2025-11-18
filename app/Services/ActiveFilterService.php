<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ActiveFilterService
{
    /**
     * Apply active/inactive filter to the query.
     *
     * @param  array<string, bool>  $filters
     */
    public function applyActiveFilter(Builder|BelongsToMany $query, array $filters): void
    {
        $table = $filters['table'] ?? null;
        $pivot = $filters['pivot'] ?? false;
        $active = $filters['active'] ?? true;
        $inactive = $filters['inactive'] ?? false;

        match (true) {
            $pivot => match (true) {
                $active && $inactive => $query,
                $active => $query->wherePivot('active', true),
                $inactive => $query->wherePivot('active', false),
                default => $query->whereRaw('1 = 0'),
            },
            is_string($table) => match (true) {
                $active && $inactive => $query,
                $active => $query->where($table.'.active', true),
                $inactive => $query->where($table.'.active', false),
                default => $query->whereRaw('1 = 0'),
            },
            default => match (true) {
                $active && $inactive => $query,
                $active => $query->where('active', true),
                $inactive => $query->where('active', false),
                default => $query->whereRaw('1 = 0'),
            },
        };
    }
}
