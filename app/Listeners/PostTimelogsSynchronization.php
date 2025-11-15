<?php

namespace App\Listeners;

use App\Events\TimelogsFlushed;
use App\Events\TimelogsSynchronized;
use App\Jobs\ProcessAffectedTimetables;
use App\Jobs\ProcessTimesheet;
use App\Models\Employee;
use App\Models\Schedule;
use App\Traits\TimelogsHasher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

class PostTimelogsSynchronization
{
    use TimelogsHasher;

    public function handle(TimelogsSynchronized|TimelogsFlushed $event): void
    {
        /**
         * MAKE TIMETABLES FOR EACH TIMESHEET PERIOD FOR EACH EMPLOYEE
         *
         *
         * UPDATE AFFECTED TIMETABLE FROM EARLIEST TO LATEST
         */
        if (in_array($event->action, ['fetch', 'import'])) {
            $timetables = $event->scanner
                ->timelogs()
                ->whereBetween('time', [
                    Carbon::parse($event->earliest)->startOfDay(),
                    Carbon::parse($event->latest)->endOfDay(),
                ])
                ->selectRaw('DATE(time) as date')
                ->distinct()
                ->reorder()
                ->pluck('date')
                ->flatMap(function ($date) use ($event) {
                    $uids = $event->scanner->timelogs()
                        ->reorder()
                        ->whereHas('employee')
                        ->whereDate('time', $date)
                        ->select('uid')
                        ->distinct();

                    return Employee::query()
                        ->whereHas('enrollments', fn ($q) => $q->where('enrollment.scanner_id', $event->scanner->id)->whereIn('enrollment.uid', $uids))
                        ->with([
                            'timelogs' => fn ($q) => $q->whereDate('time', $date),
                            'timetables' => fn ($q) => $q->whereDate('date', $date)->take(1),
                            'timetables.timelogs',
                            'timetables.timesheet',
                        ])
                        ->lazyById()
                        ->reject(function ($employee) {
                            $timetable = $employee->timetables->first();

                            return $timetable?->checkDigest();
                        })
                        ->map(function ($employee) use ($date) {
                            $dateCarbon = Carbon::parse($date);

                            $schedules = $employee->schedules()
                                ->active($dateCarbon, $dateCarbon)
                                ->get()
                                ->merge(Schedule::where('global', true)->active($dateCarbon, $dateCarbon)->get());

                            $shift = $schedules->first(fn (Schedule $schedule) => $schedule->isActive($dateCarbon));

                            return [
                                'employee_id' => $employee->id,
                                'date' => $date,
                                'shift_id' => $shift?->id,
                            ];
                        })
                        ->toArray();
                });

            if ($timetables->isEmpty()) {
                return;
            }

            $timesheets = $timetables->map(function ($timetable) {
                $date = Carbon::parse($timetable['date']);

                return $date->format('Y-m').'|'.$timetable['employee_id'];
            })
                ->unique()
                ->map(function ($employee) {
                    [$date, $employee] = explode('|', $employee);

                    $date = Carbon::parse($date);

                    $employee = Employee::find($employee);

                    $timesheet = $employee->timesheets()->firstWhere('month', $date);

                    return $timesheet && $this->checkDigest($timesheet) ? new ProcessTimesheet($employee, $date, false) : null;
                })
                ->filter();

            ProcessAffectedTimetables::dispatch($timetables->values()->all());

            if ($timesheets->isNotEmpty()) {
                Bus::batch($timesheets->values()->all())
                    ->onQueue('main')
                    ->dispatch();
            }
        }
    }
}
