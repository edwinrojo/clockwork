<?php

namespace App\Filament\Superuser\Resources\SignatureResource\Pages;

use App\Actions\OptimizeImage;
use App\Filament\Superuser\Resources\SignatureResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSignature extends EditRecord
{
    protected static string $resource = SignatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['specimen'] === $this->record->specimen) {
            return $data;
        }

        $data['specimen'] = 'data:image/x-webp;base64,'.
            base64_encode(
                app(OptimizeImage::class)(base64_decode(explode(',', $data['specimen'])[1]))
            );

        return $data;
    }
}
