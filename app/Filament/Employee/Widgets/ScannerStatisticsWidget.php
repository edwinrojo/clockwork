<?php

namespace App\Filament\Employee\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ScannerStatisticsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '45s';

    protected function getStats(): array
    {
        /** @var Employee */
        $employee = Auth::user();

        $scanners = $employee->scanners()
            ->reorder()
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->with(['timelogs' => fn ($q) => $q->reorder()->latest('time')->limit(1)])
            ->get();

        return $scanners->map(function ($scanner) {
            $latest = $scanner->timelogs->first()?->time->format('d M H:i');

            $synced = $scanner->synced_at?->diffForHumans();

            return Stat::make("$scanner->name ({$scanner->pivot->uid})", $latest ?? 'No data')
                ->description($synced && $latest ? "as of $synced" : null)
                ->descriptionIcon('gmdi-timer-o')
                ->color($scanner->priority ? 'primary' : null);
        })->toArray();
    }
}
