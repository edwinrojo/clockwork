<?php

namespace App\Actions;

use App\Events\TimelogsFlushed;
use App\Models\Scanner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FlushScannerTimelogs
{
    public function __invoke(Scanner $scanner, ?User $user = null, ?int $year = null, ?int $month = null): void
    {
        $user ??= Auth::user();

        $scanner->timelogs()
            ->withoutGlobalScopes()
            ->when($year, fn ($query) => $query->whereYear('time', $year))
            ->when($year && $month, fn ($query) => $query->whereMonth('time', $month))
            ->where(fn ($query) => 
                $query->orWhere('pseudo', true)
                    ->orWhere('recast', true)
                    ->orWhere('cloned', true)
            )
            ->update(['shadow' => true]);

        $count = $scanner->timelogs()
            ->withoutGlobalScopes()
            ->when($year, fn ($query) => $query->whereYear('time', $year))
            ->when($year && $month, fn ($query) => $query->whereMonth('time', $month))
            ->where(fn ($query) => 
                $query->where('pseudo', false)
                    ->where('recast', false)
                    ->where('cloned', false)
            )
            ->delete();

        TimelogsFlushed::dispatch($scanner, $user, $count);
    }
}
