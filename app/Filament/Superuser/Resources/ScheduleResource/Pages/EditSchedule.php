<?php

namespace App\Filament\Superuser\Resources\ScheduleResource\Pages;

use App\Filament\Superuser\Resources\ScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditSchedule extends EditRecord
{
    protected static string $resource = ScheduleResource::class;

    public function getContentTabLabel(): ?string
    {
        return 'Schedule Details';
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->schema([
                    TextInput::make('password')
                        ->visible(Filament::getCurrentOrDefaultPanel()->getId() !== 'superuser')
                        ->label('Password')
                        ->placeholder('Enter your password to confirm')
                        ->currentPassword()
                        ->rule('required')
                        ->markAsRequired(),
                ]),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['global']) && $data['global']) {
            $data['office_id'] = null;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            if (isset($data['global']) && $data['global']) {
                $record->employees()->detach();
            }

            return parent::handleRecordUpdate($record, $data);
        });
    }

    public function getRelationManagers(): array
    {
        if ($this->record->global) {
            return [];
        }

        return parent::getRelationManagers();
    }
}
