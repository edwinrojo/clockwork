<?php

namespace App\Filament\Secretary\Resources\ScheduleResource\Pages;

use App\Filament\Actions\Request\RequestAction;
use App\Filament\Secretary\Resources\ScheduleResource;
use App\Models\Schedule;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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
            RequestAction::make()
                ->type(Schedule::class)
                ->validation(fn (Schedule $record) => $record->employees->isNotEmpty())
                ->failureNotificationBody('Please assign some employees before sending a request'),
            ActionGroup::make([
                DeleteAction::make()
                    ->disabled(function () {
                        if (! $this->record->request->completed) {
                            return false;
                        }

                        return $this->record->request->user_id !== Auth::id();
                    }),
            ]),
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
