<?php

use App\Actions\GenerateQrCode;
use App\Enums\AnnotationField;
use App\Models\Holiday;

$size = isset($size)  ? mb_strtolower($size) : 'folio';

$preview ??= false;

$overtime ??= true;

$qr ??= false;

$certify ??= false;

$officer = $certify ? false : @$misc['officer'] ?? true;

$single = $preview ?: $certify ?: $signed ?: $single;

if (! $preview) {
    $seal = file_exists(storage_path('app/public/'.settings('seal')))
        ? base64_encode(file_get_contents(storage_path('app/public/'.settings('seal'))))
        : null;

    $office = $user?->employee?->currentDeployment?->office;

    $logo = ($officer) && $office?->logo && file_exists(storage_path('app/public/'.$office->logo))
        ? base64_encode(file_get_contents(storage_path('app/public/'.$office->logo)))
        : null;

    $timestamp ??= now();

    $supervisor ??= @$misc['supervisor'] ?? true;
}

$label = fn ($timesheet) => (trim($timesheet->period) ?: \Carbon\Carbon::parse($timesheet->month)->format('F Y')) .
    ($timesheet->getPeriod() === 'overtimeWork' ? ' (OT)' : '');

$generator = fn ($timesheet) => (new GenerateQrCode) (
        config('app.url')."/validation?q={$timesheet}",
        256,
        $seal ? storage_path('app/public/'.settings('seal')) : null,
        'png',
    );
?>

@extends('print.layout')

@section('content')
    @foreach ($timesheets as $timesheet)
        <div
            @class([
                'pagebreak',
                'mb-8' => $preview && !$loop->last
            ])
            @style([
                'display:flex',
                'align-items:center',
                'justify-content:center',
            ])
        >
            @for($side = 0; $side < ($single ? 1 : 2); $side++)
                <div
                    @style([
                        'width:100%',
                        'border:none;' => $size === 'a4',
                        'border-width:1pt' => ! $single,
                        'border-style:none dashed none none' => $side === 0 && ! $single,
                        'border-style:none none none dashed' => $side === 1 && ! $single,
                    ])
                >
                    <table
                        border=0
                        cellpadding=0
                        cellspacing=0
                        @style([
                            'border-collapse:collapse',
                            'table-layout:fixed',
                            'width:fit-content',
                            'margin-left:0pt' => $side == 0 && $size == 'letter',
                            'margin-left:auto;margin-right:1pt' => $side == 1 && $size == 'letter',
                            'margin-left:30pt' => $side == 0 && $size == 'a4',
                            'margin-left:auto;margin-right:31pt' => $side == 1 && $size == 'a4',
                            'margin:auto!important' => ! in_array($size, ['letter', 'a4']),
                        ])
                    >
                        @if($preview)
                            <col width=65 span={{ $overtime ? 7 : 6 }}>
                        @else
                            <col width=57 span=6>
                            <tr>
                                <td colspan=6 class="relative">
                                    <span class="absolute" style="font-size:4.65pt;opacity:0.05;right:0.65pt;">ᜑᜊᜄᜆᜅ᜔ ᜇᜊᜏ᜔</span>
                                    @if (($deployed = $timesheet->employee->currentDeployment?->office)?->logo && file_exists(storage_path('app/public/'.$deployed->logo)))
                                        <img
                                            src="data:image/png;base64,{{ base64_encode(file_get_contents(storage_path('app/public/'.$deployed->logo))) }}"
                                            alt="{{ $deployed->code }}"
                                            class="absolute"
                                            style="width:36pt;opacity:0.2;top:30pt;left:0;"
                                        >
                                    @endif
                                    @if ($seal)
                                        <img
                                            src="data:image/png;base64,{{ $seal }}"
                                            alt="{{ settings('name') }}"
                                            class="absolute"
                                            style="width:36pt;opacity:0.2;top:30pt;right:0;"
                                        >
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="italic font-xs arial" colspan=6 >
                                    Civil Service Form No. 48
                                </td>
                            </tr>
                            <tr>
                                <td class="relative center bahnschrift font-xl bold" colspan=6>
                                    <span class="absolute nowrap" style="top:8pt;left:0;right:0;margin:auto;">
                                        DAILY TIME RECORD
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="relative center font-xs bold" colspan=6>
                                    <span class="absolute" style='font-variant-ligatures:normal;font-variant-caps:normal;orphans:2;widows:2;-webkit-text-stroke-width:0px;text-decoration-thickness:initial;text-decoration-style:initial;text-decoration-color:initial;top:15pt;left:0;right:0;margin:auto;'>
                                        -----o0o-----
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan=6></td>
                            </tr>
                        @endif
                        <tr>
                            <td class="underline uppercase courier font-lg center bold" colspan={{ $preview && $overtime ? 7 : 6 }} style="text-decoration: none;">
                                {{ $timesheet->employee->name }}
                            </td>
                        </tr>
                        <tr>
                            <td class="courier top center font-xs" colspan={{ $preview && $overtime ? 7 : 6 }}>
                                Employee
                            </td>
                        </tr>
                        <tr>
                            <td class="arial font-xs bottom right" colspan={{ $preview && $overtime ? 3 : 2 }} style="padding-bottom:2.5pt;padding-right:10pt;">
                                For the month of:
                            </td>
                            <td class="underline font-md courier bold center" colspan=4 style="text-decoration: none;">
                                {{ $label($timesheet) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="font-xs left middle arial" colspan={{ $preview && $overtime ? 3 : 2 }} rowspan=2 height=40>Official hours for <br> arrival &amp; departure </td>
                            <td class="relative arial font-xs bottom left nowrap" colspan=1>
                                <span class="absolute" style="bottom:1pt;left:-11pt;">
                                    Weekdays
                                </span>
                            </td>
                            <td colspan=3 class="underline courier bold font-sm bottom left nowrap" style="text-decoration:none;letter-spacing:-0.1pt;">
                                {{ $timesheet->details['schedule']['weekdays'] ?? 'as required' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="relative arial font-xs bottom left" colspan=1>
                                <span class="absolute" style="bottom:1pt;left:-11pt;">
                                    Weekends
                                </span>
                            </td>
                            <td colspan=3 class="underline courier bold font-sm bottom left nowrap" style="text-decoration:none;letter-spacing:-0.1pt;">
                                {{ $timesheet->details['schedule']['weekends'] ?? 'as required' }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan=6></td>
                        </tr>
                        <tr class="font-sm bold">
                            <td class="border center middle courier" rowspan=2 height=42 width=58>DAY</td>
                            <td class="border center middle courier" colspan=2 width=116>AM</td>
                            <td class="border center middle courier" colspan=2 width=116>PM</td>
                            @if (!$preview && $timesheet->getPeriod() === 'overtimeWork')
                                <td class="border center middle courier" rowspan=2 width=58>Over<br>time</td>
                            @else
                                <td class="border center middle courier" rowspan=2 width=58>Under<br>time</td>
                            @endif

                            @if ($preview && $overtime)
                                <td class="border center middle courier" rowspan=2 width=58>Over<br>time</td>
                            @endif
                        </tr>
                        <tr class="font-sm bold">
                            <td class="border courier center" width=58 style="font-size:7.5pt;">Arrival</td>
                            <td class="border courier center" width=58 style="font-size:7.5pt;">Departure</td>
                            <td class="border courier center" width=58 style="font-size:7.5pt;">Arrival</td>
                            <td class="border courier center" width=58 style="font-size:7.5pt;">Departure</td>
                        </tr>

                        @for ($day = 1; $day <= 31; $day++)
                            @php($date = Carbon\Carbon::parse($timesheet->month)->setDay($day))

                            @php($timetable = $timesheet->{$timesheet->getPeriod()}->first(fn($timetable) => $timetable->date->isSameDay($date)))

                            @php($holiday = $timetable?->holiday ?: Holiday::search($date, false)?->name)

                            @if ($timesheet->from <= $day && $day <= $timesheet->to)
                                <tr
                                    @class([
                                        'weekend' => $date->isWeekend() && (@$misc['weekends'] ?? true) && ! $timetable?->holiday,
                                        'holiday' => ($holiday && (@$misc['holidays'] ?? true)),
                                        'absent' => $timetable?->absent && (@$misc['highlights'] ?? true) && (@$misc['absences'] ?? true),
                                        'annotation' => $annotation = $timesheet->annotations->first(fn ($annotation) => $annotation->date->isSameDay($date) && $annotation->field === AnnotationField::DATE),
                                        // 'invalid' => $timetable?->invalid,
                                        'font-sm' => true,
                                    ])
                                >
                                    <td
                                        @class([
                                            'border right bold',
                                            $preview ? 'font-mono' : 'courier',
                                        ])
                                        style="padding-right:14pt;padding-top:1pt;"
                                    >
                                        {{ $day }}
                                    </td>
                                    @if ($timetable?->present)
                                        @foreach(['p1', 'p2', 'p3', 'p4'] as $punch)
                                            <td
                                                width=58
                                                @class([
                                                    'relative border nowrap',
                                                    'courier' => !$preview,
                                                    'font-mono' => $preview,
                                                    'invalid' => ($timetable?->punch[$punch]['missed'] ?? false) && (@$misc['highlights'] ?? true),
                                                ])
                                                @style([
                                                    'padding-top:1pt',
                                                    $preview ? 'padding-right:5pt' : 'padding-left:5pt',
                                                    'background-color:' . ($timetable?->punch[$punch]['background'] ?? 'transparent'),
                                                    'text-color:' . ($timetable?->punch[$punch]['foreground'] ?? 'black'),
                                                ])
                                            >
                                                {{ substr($timetable->punch[$punch]['time'] ?? '', 0, strrpos($timetable?->punch[$punch]['time'] ?? '', ":")) }}
                                                @if (isset($timetable->punch[$punch]['undertime']) && ($ut = $timetable?->punch[$punch]['undertime']) > 0)
                                                    {{-- <span class="undertime-badge">
                                                        {{ $ut }}
                                                    </span> --}}
                                                @endif
                                                @if ($timetable->punch[$punch]['recast'] ?? false)
                                                    <sup @style([
                                                        'font-size:6pt',
                                                        'position:absolute',
                                                        'top:2pt',
                                                        'left:2pt',
                                                    ])>
                                                        ‽
                                                    </sup>
                                                @endif
                                                <sub @style([
                                                    'font-size:6pt',
                                                    'position:absolute',
                                                    'bottom:0',
                                                ])>
                                                    @switch(true)
                                                        @case($timetable->punch[$punch]['next'] ?? false)
                                                            N
                                                            @break
                                                        @case($timetable->punch[$punch]['previous'] ?? false)
                                                            P
                                                            @break
                                                    @endswitch
                                                </sub>
                                            </td>
                                        @endforeach
                                    @elseif($holiday && (@$misc['holidays'] ?? true) || $date->isWeekend() && (@$misc['weekends'] ?? true))
                                        <td colspan=4 @class(['border cascadia nowrap', $preview ? 'text-left px-4' : 'center']) style="overflow:hidden;text-overflow:ellipsis;">
                                            {{ $holiday ?: $date->format('l') }}
                                        </td>
                                    @elseif($annotation)
                                        <td colspan=4 @class(['border cascadia nowrap', $preview ? 'text-left px-4' : 'center']) style="overflow:hidden;text-overflow:ellipsis;">
                                            {{ $annotation->note }}
                                        </td>
                                    @else
                                        @for ($cell = 0; $cell < 4; $cell++)
                                            <td class="border">

                                            </td>
                                        @endfor
                                    @endif
                                    <td
                                        @class([
                                            'border right bold',
                                            $preview ? 'font-mono' : 'courier',
                                        ])
                                        style="padding-right:14pt;"
                                    >
                                        {{-- {{ $timesheet->getPeriod() === 'overtimeWork' && ! $preview ? $timetable?->overtime : $timetable?->undertime }} --}}
                                    </td>
                                    @if($preview && $overtime)
                                        <td
                                            @class([
                                                'border right bold',
                                                $preview ? 'font-mono' : 'courier',
                                            ])
                                            style="padding-right:14pt;"
                                        >
                                            {{ $timetable?->overtime }}
                                        </td>
                                    @endif
                                </tr>
                            @elseif(!$preview)
                                <tr>
                                    <td class="border right courier bold font-sm"
                                        style="padding-right:14pt;padding-top:1pt;"
                                    >
                                        &nbsp; {{  $day === $date->day ? $day : '--' }}
                                    </td>
                                    <td class="border courier" colspan={{ $preview ? 6 : 5 }} style="padding-left:11pt;">

                                    </td>
                                </tr>
                            @endif
                        @endfor
                        @if ($size === 'legal')
                            <tr>
                                <td colspan=6></td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan=2 class="font-md courier right bold" style="padding-right:12pt;padding-bottom:2pt;">TOTAL:</td>
                            <td colspan={{ $preview ? 5 : 4 }} @class(["underline courier left", $timesheet->getPeriod() === 'overtimeWork' ? 'font-xs' : 'font-md' ])>
                                @if ($misc['calculate'] ?? $preview)
                                    <div style="display:flex;justify-content:space-between;">
                                        {{
                                            str(
                                                collect(explode(';', $timesheet->total))
                                                    ->map(fn (string $data) => str($data)->wrap('<span>', '</span>')->replace('UT', ''))
                                                    ->join('')
                                            )->toHtmlString()
                                        }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @if (! $preview)
                            @if ($size === 'legal')
                                <tr>
                                    <td colspan=6></td>
                                </tr>
                            @endif
                            <tr>
                                <td class="italic font-xs arial" colspan=6 rowspan=3>
                                    I certify on my honor that the above is a true and correct report of the hours of work performed,
                                    record of which was made daily at the time of arrival and departure from office.
                                </td>
                            </tr>
                            <tr>
                                <td colspan=6></td>
                            </tr>
                            @if ($size === 'legal')
                                <tr>
                                    <td colspan=6></td>
                                </tr>
                            @endif
                            <tr>
                                <td colspan=6 style="height:22.5pt;"></td>
                            </tr>
                            {{-- @if (!
                                (
                                    $supervisor = $certify
                                        ? (bool) $timesheet->details['supervisor']
                                        : $supervisor
                                )
                            )
                                <tr>
                                    <td colspan=6 style="height:22.5pt;"></td>
                                </tr>
                            @endif --}}
                            <tr>
                                <td class="underline" colspan=6></td>
                            </tr>
                            <tr>
                                <td class="bahnschrift-light top center font-xs" colspan=6>Employee Signature</td>
                            </tr>
                            <tr>
                                <td class="italic arial font-xs" colspan=6>Verified as to the prescribed office hours:</td>
                            </tr>
                            <tr>
                                <td colspan=6 style="height:22.5pt;"></td>
                            </tr>
                            @if ($size === 'legal')
                                <tr>
                                    <td colspan=6></td>
                                </tr>
                            @endif
                            @php(
                                $supervisor = $certify
                                    ? (bool) $timesheet->details['supervisor']
                                    : $supervisor
                            )
                            <tr>
                                <td colspan=6 @class(['center font-sm', 'underline' => $supervisor])>
                                    @if ($supervisor)
                                        {{ $timesheet->details['supervisor'] ?? $timesheet->employee->currentDeployment?->supervisor?->titled_name }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="bahnschrift-light top center font-xs" colspan=6>
                                    @if ($supervisor)
                                        Immediate Supervisor
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td colspan=6 style="height:22.5pt;"></td>
                            </tr>
                            @if ($size === 'legal')
                                <tr>
                                    <td colspan=6></td>
                                </tr>
                            @endif
                            <tr>
                                <td colspan=6></td>
                            </tr>
                            <tr>
                                <td colspan=6 class="underline center font-sm">
                                    {{ $timesheet->details['head'] ?? (($head = $timesheet->employee->currentDeployment?->office?->head)?->is($timesheet->employee) ? null : $head?->titled_name) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="bahnschrift-light top center font-xs" colspan=6>Office Head</td>
                            </tr>
                            <tr>
                                <td colspan=1 class="relative">
                                    <div class="consolas" style="font-size:4.0pt;opacity:0.5;">
                                        @if ($timesheet->timetables->some(fn($timetable) => collect($timetable->punch)->some(fn ($punches) => isset($punches['next']))))
                                            N = Next <br>
                                        @endif
                                        @if ($timesheet->timetables->some(fn($timetable) => collect($timetable->punch)->some(fn ($punches) => isset($punches['previous']))))
                                            P = Previous <br>
                                        @endif
                                        @if ($timesheet->timetables->some(fn($timetable) => collect($timetable->punch)->some(fn ($punches) => isset($punches['recast']))))
                                            ‽ = Rectified
                                        @endif
                                    </div>
                                    <div class="absolute font-xxs consolas" style="opacity:0.3;transform:rotate(270deg);left:-17pt;top:10pt;">

                                    </div>
                                    <div class="absolute consolas" style="opacity:0.3;font-size:5pt;top:0pt;">

                                    </div>
                                </td>
                                <td colspan=2></td>
                                <td colspan=3 class="relative">
                                    @if($logo)
                                        <img
                                            src="data:image/png;base64,{{ $logo }}"
                                            alt="{{ $office->code }}"
                                            class="absolute"
                                            style="width:36pt;height:auto;opacity:0.1;margin:auto;top:5pt;left:0;right:0;"
                                        >
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td colspan=3></td>
                                <td
                                    colspan=3
                                    @class([
                                        'relative font-xs center bottom bold courier nowrap',
                                        'underline' => $officer
                                    ])
                                    @style([
                                        'color:#0007;border-color:#0007!important;' => $officer
                                    ])
                                >
                                    @if ($officer)
                                        {{ $user?->name }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td colspan=3> </td>
                                <td class="relative font-xxs center courier top nowrap" colspan=3 style="color:#0007;">
                                    @if ($certify)
                                        <image
                                            class="absolute object-contain"
                                            style="top:-45pt;right:0;max-width:72px;max-height:72px;"
                                            src="data:image/png;base64,{!!base64_encode($generator($timesheet->export->id))!!}"
                                            alt-text="validation-url"
                                        />
                                    @elseif($officer)
                                        {{ $user->position ?: $user?->employee?->designation ?? 'Staff-in-charge' }}
                                    @endif

                                    <div class="absolute consolas" style="opacity:0.8;bottom:-1pt;right:0;font-size:4.0pt;">
                                        {{ $timestamp->format('Y-m-d|H:i:s') }}
                                    </div>
                                </td>
                            </tr>
                            @if (strlen($label($timesheet)) <= 27)
                                <tr>
                                    <td colspan=6></td>
                                </tr>
                            @endif
                        @endif
                    </table>
                </div>
            @endfor
        </div>
    @endforeach
@endsection


@push('head')
    <style>
        @media print {
            @page {
                margin: 0;
                size: {{
                    match($size) {
                        'a4' => '9in 13in',         // OVERRIDE FOR BETTER FIT
                        'legal' => '8.5in 14in',
                        default => '8.5in 13in',
                    }
                }};
            }
        }
    </style>
@endpush

@if ($preview)
    @push('head')
        <style>
            .undertime-badge {
                width: 12pt !important;
                height: 12pt !important;
            }
            @media (prefers-color-scheme: dark) {
                .undertime-badge {
                    color: gray;
                    background-color: #FFF;
                }
                td {
                    border-color: #333 !important;
                }
            }
        </style>
    @endpush
@endif
