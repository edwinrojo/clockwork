<?php

namespace App\Filament\Actions\Request\TableActions;

use App\Models\Schedule;
use Filament\Actions\Action;

class ShowRoutingAction extends Action
{
    protected function setUp(): void
    {
        $this->name ??= 'view-routing';

        $this->groupedIcon('gmdi-route-o');

        $this->modalContent(function (Schedule $record) {
            return view('filament.requests.routing', [
                'requests' => $record->requests,
            ]);
        });

        $this->modalWidth('2xl');

        $this->slideOver();

        $this->modalSubmitAction(false);

        $this->modalCancelActionLabel('Close');
    }
}
