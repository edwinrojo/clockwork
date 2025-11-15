<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\User;
use App\Services\TimesheetExporter;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ExportTimesheets implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    private User $user;

    private Export $export;

    private bool $exists = false;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private TimesheetExporter $exporter,
    ) {
        $this->user = Auth::user();

        $this->queue = 'main';

        if (Export::where('details->hash', $this->exporter->id())->exists()) {
            $this->exists = true;

            $this->export = Export::where('details->hash', $this->exporter->id())->first();

            return;
        }

        $export = Export::make()
            ->forceFill([
                'filename' => '',
                'user_id' => $this->user->id,
                'details' => [
                    'hash' => $this->exporter->id(),
                ],
            ]);

        $export->save();

        $this->export = $export;
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->export->details->hash;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if ($this->exists && ! $this->exporter->skipChecks()) {
                $this->export->forceFill(['created_at' => now()])->save();
            } else {
                ['filename' => $filename, 'content' => $content] = $this->exporter->download(false);

                $this->export->update([
                    'filename' => $filename,
                    'content' => $content,
                ]);
            }

            $body = <<<HTML
                <b>{$this->export->filename}</b> <br>
                This will only be available for 15 minutes.
            HTML;

            $notification = Notification::make()
                ->icon('heroicon-o-archive-box-arrow-down')
                ->title('Timesheet export ready for download')
                ->body(str($body)->toHtmlString())
                ->actions([
                    Action::make('download')
                        ->button()
                        ->color('primary')
                        ->markAsRead()
                        ->url(route('download.export', $this->export), true),
                ]);
        } catch (Exception $exception) {
            $notification = Notification::make()
                ->danger()
                ->title('Timesheet Export Failed')
                ->body('Something went wrong. Please try again.');

            $this->export->delete();

            throw $exception;
        }

        $notification->sendToDatabase($this->user, true);

        $notification->broadcast($this->user);
    }

    public function failed(?Throwable $exception): void
    {
        $this->export->delete();

        $notification = Notification::make()
            ->danger()
            ->title('Timesheet Export Failed')
            ->body('Something went wrong. Please try again.');

        $notification->sendToDatabase($this->user, true);

        $notification->broadcast($this->user);
    }
}
