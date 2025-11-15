<?php

namespace App\Filament\Superuser\Resources\ScannerResource\Pages;

use App\Filament\Actions\FetchTimelogsAction;
use App\Filament\Actions\ImportTimelogsAction;
use App\Filament\Superuser\Resources\ScannerResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScanners extends ListRecords
{
    protected static string $resource = ScannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ImportTimelogsAction::make(),
                FetchTimelogsAction::make(),
                CreateAction::make(),
            ]),
        ];
    }
}
