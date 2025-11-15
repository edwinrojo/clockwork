<?php

namespace App\Forms\Components;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Concerns\HasExtraInputAttributes;
use Illuminate\Database\Eloquent\Collection;

class TimesheetOption extends CheckboxList
{
    use HasExtraInputAttributes;

    public ?Collection $timesheets = null;

    protected string $view = 'forms.components.timesheet-option';

    public function records(Collection $timesheets)
    {
        $this->timesheets = $timesheets;

        return $this;
    }

    public function getTimesheets(): ?Collection
    {
        return $this->timesheets;
    }
}
