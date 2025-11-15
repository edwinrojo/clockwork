<?php

namespace App\Filament\Actions\TableActions;

use App\Actions\CertifyTimesheet;
use App\Models\Timesheet;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CertifyTimesheetAction extends Action
{
    protected string|false|null $level = null;

    protected function setUp(): void
    {
        $this->level = match (Filament::getCurrentOrDefaultPanel()->getId()) {
            'director' => 'director',
            'leader' => 'leader',
            default => false,
        };

        $this->name('verify-timesheet');

        $this->label('Verify');

        $this->icon('gmdi-fact-check-o');

        $this->modalWidth('max-w-2xl');

        $this->modalIcon('gmdi-fact-check-o');

        $this->slideOver();

        $this->modalSubmitActionLabel(fn () => $this->getLabel());

        $this->successNotificationTitle('Timesheet successfully verified');

        $this->hidden(function () {
            if ($this->level === false) {
                return true;
            }
        });

        $this->modalDescription(function () {
            $prompt = <<<'HTML'
                <span class="text-sm text-custom-600 dark:text-custom-400" style="--c-400:var(--warning-400);--c-600:var(--warning-600);">
                    <br> Please review this information thoroughly before proceeding as this action cannot be undone.
                </span>
            HTML;

            return str($prompt)->toHtmlString();
        });

        $this->form(function (Timesheet $record) {
            $preview = ViewField::make('preview')
                ->view('print.csc', [
                    'timesheets' => [$record],
                    'preview' => true,
                    'overtime' => false,
                ]);

            $attachments = ViewField::make('attachments')
                ->view('filament.validation.pages.attachments', [
                    'attachments' => $record->attachments,
                ]);

            $confirmation = Checkbox::make('confirmation')
                ->extraAttributes(['class' => 'self-start mt-2'])
                ->markAsRequired()
                ->accepted()
                ->validationMessages(['accepted' => 'You must verify first.'])
                ->rule(fn () => function ($attribute, $value, $fail) use ($record) {
                    $user = user();

                    $panel = Filament::getCurrentOrDefaultPanel()->getId();

                    if ($user->signature === null || $user->signature->certificate === null || $user->signature->password === null) {
                        return $fail('You must have to configure your digital signature first.');
                    }

                    if ($panel === 'director' && @$record->details['supervisor'] && $record->leaderSigner === null) {
                        return $fail('This timesheet has not been verified by the '.settings('leader').' yet.');
                    }

                    if ($panel === 'leader' && $record->directorSigner) {
                        return $fail('This timesheet has already been verified by the '.settings('director').' ('.$record->directorSigner->signer->name.').');
                    }

                    if ($record->{$this->level.'Signer'} !== null) {
                        return $fail('This timesheet has already been verified by the '.settings($this->level).' ('.$record->{$this->level.'Signer'}->signer->name.').');
                    }
                })
                ->label(function () {
                    $label = strtolower($this->getLabel());

                    return <<<LABEL
                        I {$label} that the information provided is accurate and correctly reports
                        the hours of work performed in accordance with the prescribed office hours.
                    LABEL;
                });

            return [
                Tabs::make('Timesheet')
                    ->contained(false)
                    ->tabs([
                        Tab::make('Timesheet')
                            ->schema([
                                $preview,
                            ]),
                        Tab::make('Attachments')
                            ->schema([
                                $attachments,
                            ]),
                    ]),
                $confirmation,
            ];
        });

        $this->action(function (Action $component, CertifyTimesheet $certifier, Timesheet $timesheet, array $data) {
            $certifier($timesheet, Auth::user(), $data, $this->level);

            $user = Auth::user();

            $month = Carbon::parse($timesheet->month);

            $body = <<<HTML
                Timesheet for <b>{$month->format('Y F')}</b> has been successfully verified by {$user->name}.
            HTML;

            Notification::make()
                ->success()
                ->title('Timesheet Verified')
                ->body(str($body)->toHtmlString())
                ->sendToDatabase($timesheet->employee, true);

            $component->sendSuccessNotification();
        });
    }
}
