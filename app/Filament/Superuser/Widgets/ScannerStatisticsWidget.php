<?php

namespace App\Filament\Superuser\Widgets;

use App\Models\Scanner;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ScannerStatisticsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '45s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $superuser = Filament::getCurrentOrDefaultPanel()->getId() === 'superuser';

        $scope = function (Builder $query) {
            $query->orWhere(function ($query) {
                $query->whereIn('scanners.id', user()->scanners()->pluck('scanners.id'));
            });

            $query->orWhere(function ($query) {
                $query->whereHas('employees', function ($query) {
                    $query->whereHas('offices', function ($query) {
                        $query->whereIn('offices.id', user()->offices()->pluck('offices.id'));
                    });
                });
            });
        };

        $scanners = Scanner::query()
            ->when(! $superuser, $scope)
            ->whereNotNull('uid')
            ->reorder()
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->with(['timelogs' => fn ($q) => $q->reorder()->latest('time')->limit(1)])
            ->withCount('timelogs')
            ->get();

        return $scanners->map(function ($scanner) use ($superuser) {
            $name = mb_strtoupper($scanner->name);

            $latest = $scanner->timelogs->first()?->time->format('d M H:i');

            $synced = $scanner->synced_at?->diffForHumans();

            $uid = str_pad($scanner->uid, 3, '0', STR_PAD_LEFT);

            $uid = $superuser ? "<span class='text-sm text-custom-600 dark:text-custom-400' style='--c-400:var(--primary-400);--c-600:var(--primary-600);'>{$uid}</span> " : '';

            $description = $synced && $latest ? match ($superuser) {
                true => <<<HTML
                    {$scanner->timelogs_count} records as of $synced <br>
                    {$scanner->synced_at?->format('jS \of F Y @H:i:s')}
                HTML,
                default => <<<HTML
                    as of $synced <br>
                    {$scanner->synced_at?->format('jS \of M Y @H:i:s')}
                HTML,
            } : null;

            return Stat::make(str("{$uid}{$name}")->toHtmlString(), $latest ?? 'No data')
                ->description(str($description)->toHtmlString());
        })->toArray();
    }
}
