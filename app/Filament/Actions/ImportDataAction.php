<?php

namespace App\Filament\Actions;

use App\Enums\UserRole;
use App\Jobs\ImportData;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportDataAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name ??= 'import-data';

        $this->icon('heroicon-m-arrow-up-tray');

        $this->groupedIcon('heroicon-m-arrow-up-tray');

        $this->visible(user()->hasAnyRole(UserRole::ROOT, UserRole::DEVELOPER));

        $this->modalDescription(function () {
            $html = <<<'HTML'
                Import data from a CSV file.

                <span class="block text-custom-600 dark:text-custom-400" style="--c-400:var(--danger-400);--c-600:var(--danger-600);">
                    Overwritten conflicting resources is not reversible.
                </span>
            HTML;

            return str($html)->toHtmlString();
        });

        $this->schema([
            FileUpload::make('file')
                ->acceptedFileTypes(['text/csv', 'text/x-csv', 'application/csv', 'application/x-csv', 'text/comma-separated-values', 'text/x-comma-separated-values', 'text/plain'])
                ->placeholder('Upload data from CSV file')
                ->storeFiles(false)
                ->visibility('private')
                ->required()
                ->helperText('Updates to soft deleted resources are ignored.'),
        ]);

        $this->action(function (array $data) {
            if (! $data['file'] instanceof TemporaryUploadedFile) {
                return;
            }

            ImportData::dispatch($data['file']->getRealPath(), $data['file']->getClientOriginalName());

            Notification::make()
                ->success()
                ->title('Upload successful')
                ->body("We'll notify you once all the data have been processed completely.")
                ->send();
        });
    }
}
