<?php

namespace App\Filament\Employee\Resources\TimelogResource\Pages;

use App\Filament\Employee\Resources\TimelogResource;
use App\Filament\Employee\Widgets\ScannerStatisticsWidget;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTimelogs extends ListRecords
{
    protected static string $resource = TimelogResource::class;

    public function getBreadcrumb(): ?string
    {
        return Filament::auth()->user()->titled_name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    protected function getFooterWidgets(): array
    {
        return [
            ScannerStatisticsWidget::class,
        ];
    }
}
