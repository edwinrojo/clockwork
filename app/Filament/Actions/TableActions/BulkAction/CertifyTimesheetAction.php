<?php

namespace App\Filament\Actions\TableActions\BulkAction;

use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;

class CertifyTimesheetAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->visible(in_array(Filament::getCurrentOrDefaultPanel()->getId(), ['director', 'leader']));

        $this->name('timesheet-certificator');

        $this->label('Verify Timesheet');

        $this->icon('gmdi-fact-check-o');

        $this->modalIcon('gmdi-fact-check-o');

        $this->action(function (Collection $records) {
            if ($records->count() > 25) {
                Notification::make()
                    ->title('Too many records selected')
                    ->body('Please select 25 records or less.')
                    ->danger()
                    ->send();

                return;
            }

            $panel = Filament::getCurrentOrDefaultPanel()->getId();

            if (! in_array($panel, ['director', 'leader'])) {
                Notification::make()
                    ->title('Something went wrong')
                    ->danger()
                    ->send();

                return;
            }

            return redirect(URL::signedRoute(
                "filament.{$panel}.resources.timesheets.verify",
                ['timesheets' => encrypt($records->pluck('id')->toArray())]
            ));
        });
    }
}
