<?php

namespace App\Jobs;

use App\Actions\ProcessTimetable as ProcessTimetableAction;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProcessAffectedTimetables implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array<int, array{employee_id: int, date: string, shift_id?: int|null}>  $timetables
     */
    public function __construct(
        private readonly array $timetables,
    ) {
        $this->queue = 'main';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $action = app(ProcessTimetableAction::class);

        foreach ($this->timetables as $timetable) {
            $employee = Employee::find($timetable['employee_id']);
            $date = Carbon::parse($timetable['date']);

            $shift = isset($timetable['shift_id']) && $timetable['shift_id']
                ? Schedule::find($timetable['shift_id'])
                : null;

            if ($employee) {
                $action($employee, $date, $shift);
            }
        }
    }
}

