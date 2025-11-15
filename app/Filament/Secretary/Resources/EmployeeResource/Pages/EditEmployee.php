<?php

namespace App\Filament\Secretary\Resources\EmployeeResource\Pages;

use App\Filament\Secretary\Resources\EmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getContentTabLabel(): ?string
    {
        return 'Employee Account';
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
