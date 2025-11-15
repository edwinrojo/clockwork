<?php

namespace App\Filament\Actions\TableActions;

use App\Models\Timesheet;
use Filament\Actions\Action;
use Illuminate\Support\Carbon;
use ZipArchive;

class DownloadTimesheetAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name('download-timesheet');

        $this->requiresConfirmation();

        $this->color('gray');

        $this->hidden(fn (Timesheet $record) => $record->exports->isEmpty());

        $this->requiresConfirmation();

        $this->icon('heroicon-o-document-arrow-down');

        $this->modalIcon('heroicon-o-document-arrow-down');

        $this->modalSubmitActionLabel('Download');

        $this->failureNotificationTitle('Download Failed');

        $this->modalDescription(function (Timesheet $record) {
            $period = match ($record->span) {
                'full' => 'month',
                default => "{$record->span} half",
            };

            $month = Carbon::parse($record->month)->format('F Y');

            $description = <<<HTML
                Are you sure you want to download the timesheet of <br>
                {$record->employee->titled_name} for the {$period} of {$month}?
            HTML;

            return str($description)->toHtmlString();
        });

        $this->action(function (Timesheet $record) {
            $from = str_pad($record->from, 2, '0', STR_PAD_LEFT);

            $tmp = tempnam(sys_get_temp_dir(), 'zip_');

            $name = "{$record->month} {$from}-{$record->to} ".trim($record->employee->name, '.').'.zip';

            $headers = ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="'.$name.'"'];

            $zip = new ZipArchive;

            if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
                $this->sendFailureNotification();

                return;
            }

            $filename = function ($filename): string {
                $extension = pathinfo($filename, PATHINFO_EXTENSION);

                $filename = pathinfo($filename, PATHINFO_FILENAME);

                return str($filename)->trim()->unwrap('(', ')')->append(".$extension");
            };

            $zip->addFromString($filename($record->export->filename), $record->export->content);

            $record->attachments->each(function ($accomplishment) use ($filename, $zip) {
                $zip->addFromString($filename($accomplishment->filename), $accomplishment->content);
            });

            $zip->close();

            return response()->download($tmp, $name, $headers)->deleteFileAfterSend();
        });
    }
}
