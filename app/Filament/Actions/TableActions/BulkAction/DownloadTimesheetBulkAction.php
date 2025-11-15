<?php

namespace App\Filament\Actions\TableActions\BulkAction;

use App\Models\Timesheet;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use ZipArchive;

class DownloadTimesheetBulkAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download');

        $this->name('download-timesheets');

        $this->requiresConfirmation();

        $this->icon('heroicon-o-document-arrow-down');

        $this->requiresConfirmation();

        $this->modalIcon('gmdi-fact-check-o');

        $this->modalHeading('Download certified timesheets');

        $this->modalDescription('Download selected timesheets.');

        $this->successNotificationTitle('Timesheets will be downloaded shortly.');

        $this->failureNotificationTitle('Download failed.');

        $this->modalWidth('max-w-lg');

        $this->action(function (BulkAction $action, Collection $records) {
            if ($records->isEmpty()) {
                $action->sendFailureNotification();

                return;
            }

            $temp = stream_get_meta_data(tmpfile())['uri'];

            $zip = new ZipArchive;

            $zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $zip->setCompressionIndex(-1, ZipArchive::CM_STORE);

            $filename = function ($filename, $name): string {
                $extension = pathinfo($filename, PATHINFO_EXTENSION);

                $filename = pathinfo($filename, PATHINFO_FILENAME);

                return str($filename)->trim()->unwrap('(', ')')->prepend("$name/")->append(".$extension");
            };

            $records->each(function (Timesheet $timesheet) use ($filename, $zip) {
                $zip->addFromString($filename($timesheet->export->filename, $timesheet->employee->name), $timesheet->export->content);

                $timesheet->attachments->each(function ($accomplishment) use ($filename, $timesheet, $zip) {
                    $zip->addFromString($filename($accomplishment->filename, "{$timesheet->employee->name}/attachments"), $accomplishment->content);
                });
            });

            $zip->close();

            $action->sendSuccessNotification();

            $name = 'Timesheets.zip';

            $headers = ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="'.$name.'"'];

            return response()->download($temp, $name, $headers)->deleteFileAfterSend();
        });
    }
}
