<?php

namespace App\Filament\Superuser\Resources\SignatureResource\Pages;

use App\Filament\Superuser\Resources\SignatureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSignatures extends ListRecords
{
    protected static string $resource = SignatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
