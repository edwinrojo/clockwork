<?php

namespace App\Actions;

use App\Helpers\GetRawAttendancePunch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\Timetable;
use App\Traits\TimelogsHasher;
use Illuminate\Support\Carbon;

class ProcessTimetable
{
    use TimelogsHasher;

    public function __invoke(Employee $employee, Carbon $date, ?Schedule $shift = null): void
    {
        $schedule = $shift ?? Schedule::search($date, $employee);

        $sheet = $employee->timesheets()->firstOrCreate(['month' => $date->clone()->startOfMonth()]);

        $timetable = Timetable::firstOrCreate(['date' => $date, 'timesheet_id' => $sheet->id], ['punch' => []]);

        switch ($schedule?->arrangement) {
            case 'standard-work-hour':
                $this->standard($employee, $date, $schedule, $timetable);
                break;
            case 'work-shifting':
                $this->shift($employee, $date, $schedule, $timetable);
                break;
            default:
                $this->fallback($employee, $date, $timetable);
        }
    }

    protected function fallback(Employee $employee, Carbon $date, Timetable $timetable): void
    {
        $timelogs = $employee->timelogs()
            ->whereDate('time', $date)
            ->with('scanner')
            ->get();

        if ($timelogs->isEmpty()) {
            $timetable->delete();

            return;
        }

        $roster = app(GetRawAttendancePunch::class)($timelogs, $date);

        $timetable->update(['punch' => $roster, 'digest' => $this->generateDigest($timetable, $timelogs), 'absent' => false]);
    }

    protected function standard(Employee $employee, Carbon $date, Schedule $schedule, Timetable $timetable): void
    {
        $timelogs = $timetable->timelogs;

        $holiday = Holiday::search($date);

        if ($timelogs->isEmpty()) {
            $timetable->delete();

            return;
        }

        $roster = [];

        foreach ($punches = $schedule->timetable['break'] > 0 ? ['p1', 'p2', 'p3', 'p4'] : ['p1', 'p4'] as $state) {
            $punchTime = $date->clone()->setTime(...explode(':', $schedule->timetable[$state]));

            $timelists = $timelogs->reject(fn ($punch) => in_array($punch->id, array_column($roster, 'id')))
                ->filter(fn ($punch) => ((int) $punchTime->diffInMinutes($punch->time)) <= ($schedule->threshold[$state]['max'] ?? INF))
                ->filter(fn ($punch) => ((int) $punch->time->diffInMinutes($punchTime)) <= ($schedule->threshold[$state]['min'] ?? INF));

            $timelists = match ($state) {
                'p1', 'p3' => $timelists->filter(fn ($punch) => $punch->in),
                'p2', 'p4' => $timelists->filter(fn ($punch) => $punch->out),
            };

            $timelists = match ($state) {
                'p1' => $timelists->sortBy(fn ($punch) => $punch->time->clone()->subYears((int) $punch->scanner->priority)),
                'p2' => $timelists->sortBy(fn ($punch) => $punchTime->diffInSeconds($punch->time->clone()->addYears((int) $punch->scanner->priority))),
                'p3' => $timelists->sortBy(fn ($punch) => $punchTime->diffInSeconds($punch->time->clone()->subYears((int) $punch->scanner->priority))),
                'p4' => $timelists->sortByDesc(fn ($punch) => $punch->time->clone()->addYears((int) $punch->scanner->priority)),
            };

            $punched = $timelists->reject(fn ($punch) => (($log = end($roster))) && $punch->time->format('H:i:s') < ($log['time'] ?? '00:00:00'))->first();

            if (is_null($punched)) {
                $roster[$state] = ['missed' => true];

                continue;
            }

            $roster[$state] = [
                'id' => $punched->id,
                'time' => $punched->time->format('H:i:s'),
                'foreground' => $punched->scanner?->foreground_color,
                'background' => $punched->scanner?->background_color,
                'recast' => $punched->recast,
            ];

            $roster[$state]['undertime'] = match ($state) {
                'p1', 'p3' => ($diff = (int) $punchTime->diffInMinutes($punched->time)) > ($schedule->threshold[$state]['tardy'] ?? 0) ? $diff : 0,
                'p2', 'p4' => ($diff = (int) $punched->time->setSeconds(0)->diffInMinutes($punchTime)) > 0 ? $diff : 0,
                default => null,
            };
        }

        $shift1 = 0;
        $shift2 = 0;

        if ($schedule->timetable['break'] > 0) {
            $shift1 = match (isset($roster['p1']['time']) && isset($roster['p2']['time'])) {
                true => $date->clone()->setTime(...explode(':', $schedule->timetable['p1']))
                    ->diffInMinutes($date->clone()->setTime(...explode(':', $schedule->timetable['p2'])), false),
                false => 0,
            };

            $shift2 = match (isset($roster['p3']['time']) && isset($roster['p4']['time'])) {
                true => $date->clone()->setTime(...explode(':', $schedule->timetable['p3']))
                    ->diffInMinutes($date->clone()->setTime(...explode(':', $schedule->timetable['p4']))),
                false => 0,
            };

            $total = ($shift1 ? $shift1 - $roster['p1']['undertime'] - $roster['p2']['undertime'] : 0) +
                ($shift2 ? $shift2 - $roster['p3']['undertime'] - $roster['p4']['undertime'] : 0);
        } else {
            $total = match (isset($roster['p1']['time']) && isset($roster['p4']['time'])) {
                true => $date->clone()->setTime(...explode(':', $schedule->timetable['p1']))
                    ->diffInMinutes($date->clone()->setTime(...explode(':', $schedule->timetable['p4'])))
                    - $roster['p1']['undertime'] - $roster['p4']['undertime'],
                false => 0,
            };
        }

        $out = $date->clone()->setTime(...explode(':', $schedule->timetable['p4']));

        $check = array_intersect_key(array_flip($punches), collect($roster)->reject(fn ($punch) => isset($punch['missed']))->toArray());

        $regular = $date->isWeekday() && $holiday->isEmpty();

        $undertime = array_sum(array_column($roster, 'undertime'));

        if ($regular) {
            $excess = isset($roster['p4']['time']) ? (int) $out->diffInMinutes($out->clone()->setTime(...explode(':', $roster['p4']['time']))) : 0;

            $overtime = match ($excess >= ($schedule->threshold['overtime']['min'] ?? INF)) {
                false => 0,
                default => match ($excess >= ($schedule->threshold['overtime']['max'] ?? 0)) {
                    false => $excess,
                    default => (int) $schedule->threshold['overtime']['max'],
                }
            };
        } else {
            $overtime = ($ot = ($shift1 + $shift2 - $undertime)) > $schedule->timetable['duration'] * 60 ? (int) ($ot / 60) * 60 : $ot;
        }

        $timetable->update([
            'punch' => $roster,
            'half' => $half = $check == ['p1' => 0, 'p2' => 1] || $check == ['p3' => 2, 'p4' => 3],
            'regular' => $regular,
            'invalid' => ! $half && $check !== array_flip($punches),
            'undertime' => array_sum(array_column($roster, 'undertime')),
            'overtime' => max($overtime, 0),
            'duration' => $total,
            'holiday' => $holiday->map->name->join(', ') ?: null,
            'present' => true,
            'absent' => false,
            'digest' => $this->generateDigest($timetable, $timelogs),
        ]);
    }

    protected function shift(Employee $employee, Carbon $date, Schedule $schedule, Timetable $timetable): void
    {
        $p1 = $schedule->pivot->timetable['p1']['time'];

        $p2 = $schedule->pivot->timetable['p2']['time'];

        $timelogs = $employee->timelogs()
            ->whereBetween('time', [
                $date->clone()->setTime(...explode(':', $p1))->subMinutes((int) $schedule->threshold['p1']['min']),
                $date->clone()->addDays($p1 > $p2 ? 1 : 0)->setTime(...explode(':', $p2))->addMinutes((int) $schedule->threshold['p2']['max']),
            ])
            ->with('scanner')
            ->get();

        $holiday = Holiday::search($date);

        if ($timelogs->isEmpty()) {
            $timetable->delete();

            return;
        }

        $roster = [];

        foreach (['p1', 'p2'] as $state) {
            $punchTime = $date->clone()->addDays($state === 'p2' && $p1 > $p2 ? 1 : 0)->setTime(...explode(':', $schedule->pivot->timetable[$state]['time']));

            $timelists = $timelogs->reject(fn ($punch) => in_array($punch->id, array_column($roster, 'id')))
                ->filter(fn ($punch) => ((int) $punchTime->diffInMinutes($punch->time)) <= ($schedule->threshold[$state]['max'] ?? INF))
                ->filter(fn ($punch) => ((int) $punch->time->diffInMinutes($punchTime)) <= ($schedule->threshold[$state]['min'] ?? INF));

            $timelists = match ($state) {
                'p1' => $timelists->filter(fn ($punch) => $punch->in)
                    ->sortBy(fn ($punch) => $punch->time->clone()->subYears((int) $punch->scanner->priority)),
                'p2' => $timelists->filter(fn ($punch) => $punch->out)
                    ->sortByDesc(fn ($punch) => $punchTime->diffInSeconds($punch->time->clone()->addYears((int) $punch->scanner->priority))),
            };

            $punched = $timelists->reject(fn ($punch) => ($log = end($roster)) ? $punch->time->format('Y-m-d H:i:s') < ($log['time'] ?? '0000-00-00 00:00:00') : false)->first();

            if (is_null($punched)) {
                $roster[$schedule->pivot->timetable[$state]['alias']] = ['missed' => true];

                continue;
            }

            $roster[$schedule->pivot->timetable[$state]['alias']] = [
                'id' => $punched->id,
                'time' => $punched->time->format('Y-m-d H:i:s'),
                'foreground' => $punched->scanner?->foreground_color,
                'background' => $punched->scanner?->background_color,
                'recast' => $punched->recast,
            ];

            if ($state === 'p1' && $punched->time->clone()->addDay()->isSameDay($date)) {
                $roster[$schedule->pivot->timetable[$state]['alias']]['previous'] = 1;
            }

            if ($state === 'p2' && $punched->time->clone()->subDay()->isSameDay($date)) {
                $roster[$schedule->pivot->timetable[$state]['alias']]['next'] = 1;
            }

            $roster[$schedule->pivot->timetable[$state]['alias']]['undertime'] = match ($state) {
                'p1' => ($diff = (int) $punchTime->diffInMinutes($punched->time)) > ($schedule->threshold[$state]['tardy'] ?? 0) ? $diff : 0,
                'p2' => ($diff = (int) $punched->time->diffInMinutes($punchTime)) > 0 ? $diff : 0,
                default => null,
            };
        }

        $roster = collect($roster)->map(function ($punch) {
            if (isset($punch['time'])) {
                $punch['time'] = explode(' ', $punch['time'])[1];
            }

            return $punch;
        })->toArray();

        $check = array_intersect_key(array_flip(array_column($schedule->pivot->timetable, 'alias')), $roster);

        $regular = $date->isWeekday() && $holiday->isEmpty();

        $out = $date->clone()->setTime(...explode(':', $p2));

        $undertime = array_sum(array_column($roster, 'undertime'));

        if ($regular) {
            $excess = isset($roster['p2']['time']) ? (int) $out->diffInMinutes($out->clone()->setTime(...explode(':', $roster['p2']['time']))) : 0;

            $overtime = $excess > ($schedule->threshold['overtime'] ?? INF) ? (int) ($excess / 60) * 60 : 0;
        } elseif (isset($roster['p1']['time']) && isset($roster['p2']['time'])) {
            $overtime = ($ot = $date->clone()->setTime(...explode(':', $p1))
                ->diffInMinutes($date->clone()->setTime(...explode(':', $p2)), false) -
                    $undertime) > $schedule->timetable['duration'] * 60 ? (int) ($ot / 60) * 60 : $ot;
        } else {
            $overtime = 0;
        }

        $timetable->update([
            'punch' => $roster,
            'half' => false,
            'invalid' => $check !== array_flip(array_column($schedule->pivot->timetable, 'alias')),
            'undertime' => $undertime,
            'regular' => $regular,
            'overtime' => max($overtime, 0),
            'holiday' => $holiday->map->name->join(', ') ?: null,
            'present' => true,
            'absent' => false,
            'digest' => $this->generateDigest($timetable, $timelogs),
        ]);
    }
}

