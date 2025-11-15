<?php

namespace App\Jobs;

use App\Actions\ProcessTimetable as ProcessTimetableAction;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Traits\TimelogsHasher;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProcessTimesheet implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TimelogsHasher;

    private readonly Carbon $month;

    private readonly Timesheet $sheet;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Employee $employee,
        Carbon|string $month,
        private bool $process = true,
    ) {
        $this->queue = 'main';

        $this->month = is_string($month) ? Carbon::parse($month)->startOfMonth() : $month;

        $this->sheet = $this->employee->timesheets()->firstOrCreate(['month' => $this->month->startOfMonth(), 'span' => 'full']);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->employee->id.'-'.$this->month->format('Y-m');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $schedules = Schedule::search(
            employee: $this->employee,
            date: $this->month->clone()->startOfMonth(),
            until: $this->month->clone()->endOfMonth(),
        );

        $sheet = $this->sheet;

        $time = function (string $week) use ($schedules) {
            return match (true) {
                $schedules?->$week?->count() === 1 => $schedules?->$week?->first()->time,
                $schedules?->$week?->filter(fn ($schedule) => $schedule->{str($week)->singular()->toString()})->count() === 1 => $schedules?->$week?->filter(fn ($schedule) => $schedule->{str($week)->singular()->toString()})->first()?->time,
                default => 'as required'
            };
        };

        $sheet->update([
            'details' => [
                'supervisor' => $this->employee->supervisor?->titled_name,
                'head' => $this->employee->currentOffice?->head?->id !== $this->employee->id ? $this->employee->currentOffice?->head?->titled_name : '',
                'schedule' => ['weekdays' => $time('weekdays'), 'weekends' => $time('weekends')],
            ],
            'digest' => $this->generateDigest($sheet),
        ]);

        if ($this->process === false) {
            return;
        }

        $tables = $sheet->timetables->load('timelogs');

        $days = collect($this->month->range($this->month->clone()->endOfMonth()))
            // TODO
            // ADD GROUPING LOGIC HERE FOR CUSTOM SCHEDULES (CONTINUOUS MULTIPLE-DAY SPAN SHIFTS)
            ->reject(function ($day) use ($tables) {
                $table = $tables->first(fn ($table) => $table->date->isSameDay($day));

                if (is_null($table)) {
                    return false;
                }

                return $this->checkDigest($table);
            });

        $action = app(ProcessTimetableAction::class);

        $employeeSchedules = $this->employee->schedules()
            ->active($this->month->clone()->startOfMonth(), $this->month->clone()->endOfMonth())
            ->get()
            ->merge(Schedule::where('global', true)->active($this->month->clone()->startOfMonth(), $this->month->clone()->endOfMonth())->get());

        $days->each(function ($day) use ($action, $employeeSchedules) {
            $shift = $employeeSchedules->first(fn (Schedule $schedule) => $schedule->isActive($day));

            $action($this->employee, $day, $shift);
        });

    }
}
