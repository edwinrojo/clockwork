@extends('print.layout')

@use('App\Models\Office')

@php($size ??= 'a4')

@php($preview ??= false)

@php($pagination = match($size) { 'folio' => 45, 'legal' => 50, 'a4' => 40, default => 35 } * 2)

@php($dates ??= [])

@php($from ??= null)

@php($to ??= null)

@php($modes ??= [])

@php($scanners ??= [])

@php($states ??= [])

@php($scope ??= null)

@php($filter = function (&$query, $date, $filter = false) use ($from, $to, $modes, $scanners, $states, $scope) {
    $query->whereDate('time', $date);

    $query->when($from, fn ($q) => $q->whereTime('time', '>=', $from));

    $query->when($to, fn ($q) => $q->whereTime('time', '<=', $to));

    $query->when($modes, fn ($q) => $q->whereIn('mode', $modes));

    $query->when($states, fn ($q) => $q->whereIn('state', $states));

    $query->when(is_array($scanners) ? count($scanners) : $scanners->isNotEmpty(), fn ($q) => $q->whereIn('timelogs.device', $scanners->pluck('uid')->toArray()));

    $query->when($filter, fn ($q) => $q->limit(1));

    if (
        collect($states)
            ->isNotEmpty() &&
        collect($states)
            ->map(fn ($state) => $state instanceof \UnitEnum ? $state : \App\Enums\TimelogState::from($state))
            ->ensure(\App\Enums\TimelogState::class)
            ->every(fn ($state) => $state->out())
    ) {
        $query->reorder()->orderByDesc('time');
    } else {
        $query->reorder()->orderBy('time');
    }
})

@section('content')
    @foreach ($offices as $office)
        @foreach (collect($dates)->sort() as $date)
            @php(
                $employees = $office->employees()
                    ->with(['timelogs' => fn ($query) => $filter($query, $date)])
                    ->when($strict ??= false, fn ($query) => $query->whereHas('timelogs', fn ($query) => $filter($query, $date, true)))
                    ->when($status ??= null, fn ($q) => is_array($status) ? $q->whereIn('status', $status) : $q->where('status', $status))
                    ->when(($substatus ??= null) && $status, fn ($q) => is_array($substatus) ? $q->whereIn('substatus', $substatus) : $q->where('substatus', $substatus))
                    ->when(($current ??= false) && (get_class($office) === Office::class), fn ($q) => $q->where('deployment.current', true))
                    ->when($scope, $scope)
                    ->get()
                    ->when($strict, fn ($employees) => $employees->reject(fn ($employee) => $employee->timelogs->isEmpty())) // bugged - don't remove
                    ->values()
            )

            @foreach ($employees->chunk($preview ? $employess->count() : $pagination) as $page => $chunked)
                <div class="pagebreak" style="display:flex;align-items:center;justify-content:center;max-width:620pt;margin:auto;">
                    <table
                        border="0"
                        cellpadding="0"
                        cellspacing="0"
                        @style([
                            'border-collapse:collapse',
                            'table-layout:fixed',
                            'width:fit-content',
                            'overflow:hidden',
                        ])
                    >
                        <col width={{ $size === 'a4' ? 72 : 74 }} span=10>
                        <tr></tr>
                        <tr>
                            <td colspan="10" class="relative right">
                                <span class="absolute" style="font-size:4.65pt;opacity:0.15;left:6pt;">ᜑᜊᜄᜆᜅ᜔ ᜇᜊᜏ᜔</span>

                                @if (file_exists(storage_path('app/public/'.settings('seal'))))
                                    <img
                                        src="data:image/png;base64,{{ base64_encode(file_get_contents(storage_path('app/public/'.settings('seal')))) }}"
                                        src="{{ url('storage/'.settings('seal')) }}"
                                        alt="davao-del-sur"
                                        class="absolute"
                                        style="width:48pt;top:15pt;left:0;"
                                    >
                                @endif

                                @if (($logo = $user?->employee?->currentDeployment?->office->logo) && file_exists(storage_path('app/public/'.$logo)))
                                    <img
                                        src="data:image/png;base64,{{ base64_encode(file_get_contents(storage_path('app/public/'.$logo))) }}"
                                        alt="davao-del-sur"
                                        class="absolute"
                                        style="width:48pt;top:15pt;right:0;"
                                    >
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="10" class="uppercase center consolas">
                                Republic of the Philippines
                            </td>
                        </tr>
                        <tr>
                            <td colspan="10" class="uppercase center courier bold font-xl" @style(['opacity:0.25' => empty(settings('name'))])>
                                {{ settings('name') ?? 'Agency name not set' }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="10" class="uppercase center bahnschrift-light font-sm" @style(['opacity:0.25' => empty(settings('address'))])>
                                {{ settings('address') ?? 'Agency address not set' }}
                            </td>
                        </tr>
                        @if ($size !== 'letter')
                            <tr></tr>
                        @endif
                        <tr>
                            <td colspan="10" class="underline uppercase courier center nowrap font-lg">
                                {{ $user?->employee?->currentDeployment?->office->name }}
                            </td>
                        </tr>
                        <tr></tr>
                        <tr>
                            <td rowspan="3" colspan="10" class="arial" style="padding:0 1rem;vertical-align:top;">
                                <div class="italic center font-lg" style="text-underline-offset:2pt;margin:0;">
                                    <span class="bold" style="text-transform: capitalize">{{ $office->name }}</span>
                                </div>
                                <div class="center font-sm" style="margin-bottom:3pt;">
                                    Office Attendance for <span class="bold">{{ \Carbon\Carbon::parse($date)->format('j F Y') }}</span>

                                    @if (is_array($scanners) ? count($scanners) :$scanners->isNotEmpty())
                                        from scanners
                                        <span class="italic bold">
                                            {{ $scanners->map(fn ($scanner) => mb_strtolower($scanner->name))->sort()->join(', ') }}
                                        </span>
                                    @endif

                                    @if ($states)
                                        via states
                                        <span class="italic bold">
                                            {{
                                                collect($states)
                                                    ->map(fn ($state) => is_string($state) || is_numeric($state) ? \App\Enums\TimelogState::tryFrom($state)?->getLabel() : $state->getLabel())
                                                    ->join(', ')
                                            }}
                                        </span>
                                    @endif

                                    @if ($modes)
                                        via modes
                                        <span class="italic bold">
                                            {{
                                                collect($modes)
                                                    ->map(fn ($mode) => is_string($mode) || is_numeric($state) ? \App\Enums\TimelogMode::tryFrom($mode)?->getLabel() : $mode->getLabel())
                                                    ->unique()
                                                    ->join(', ')
                                            }}
                                        </span>
                                    @endif

                                    @if ($status)
                                        for
                                        <span class="italic bold">
                                            {{
                                                collect($status)
                                                    ->map(fn ($status) => ucfirst($status instanceof \UnitEnum ? $status->value : $status))
                                                    ->map(fn ($status) =>
                                                        $status === 'Contractual' && isset($substatus)
                                                            ? "$status (" . collect($substatus)
                                                                ->map(fn ($substatus) => ucfirst($substatus instanceof \UnitEnum ? $substatus->value : $substatus))
                                                                ->join(', ') . ")"
                                                            : $status
                                                    )
                                                    ->join(', ', count($status) > 2 ? ', and ' : ' and ')
                                            }}
                                        </span>
                                        employees
                                    @endif

                                    @if($from)
                                        starting <span class="italic bold"> {{ $from }} </span>
                                    @endif

                                    @if($to)
                                        until <span class="italic bold"> {{ $to }} </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr></tr>
                        <tr></tr>
                        <tr></tr>
                        @for ($i = 0; $i < $pagination / 2; $i++)
                            @php($employee_1 = @$chunked[$i + ($pagination * $page)])

                            @php($employee_2 = @$chunked[$i + ($pagination * $page) + $pagination / 2])
                            <tr>
                                <td colspan="4">
                                    @if ($employee_1)
                                        {{ str($i + ($pagination * $page) + 1)->padLeft(2, 0) }}. {{ $employee_1->name }}
                                    @elseif($i + ($pagination * $page) === $employees->count())
                                        <span class="italic consolas font-xs">Nothing follows...</span>
                                    @endif
                                </td>
                                <td class="courier">
                                    {{ $employee_1?->timelogs->first()?->time?->format('H:i') }}
                                </td>
                                <td colspan="4">
                                    @if ($employee_2)
                                        {{ str($i + ($pagination * $page) + $pagination / 2 + 1)->padLeft(2, 0) }}. {{ $employee_2?->name }}
                                    @elseif(($i + ($pagination * $page) + $pagination / 2) === $employees->count() && $employees->count() !== $page * $pagination + $pagination / 2)
                                        <span class="italic consolas font-xs">Nothing follows...</span>
                                    @endif
                                </td>
                                <td class="courier">
                                    {{ $employee_2?->timelogs->first()?->time?->format('H:i') }}
                                </td>
                            </tr>
                        @endfor
                        <tr>
                            <td colspan="5">
                                @if (($i + ($pagination * $page)) === $employees->count() && $employees->count() === $page * $pagination + $pagination / 2)
                                    <span class="italic consolas font-xs">Nothing follows...</span>
                                @endif
                            </td>
                            <td colspan="5">
                                @if (($i + ($pagination * $page) + $pagination / 2) === $employees->count() && $employees->count() === ($page + 1) * $pagination)
                                    <span class="italic consolas font-xs">Nothing follows...</span>
                                @endif
                            </td>
                        </tr>
                        @if (! $preview)
                            <tr></tr>
                            <tr>
                                <td colspan="10" class="center courier bold font-sm">
                                    @if (($pages = ceil($employees->count() / $pagination)) > 1)
                                        Page {{ $page + 1 }} / {{ $pages }}
                                    @endif
                                </td>
                            </tr>
                            <tr></tr>
                            @if (in_array($size, ['folio', 'legal', 'letter']))
                                <tr></tr>
                            @endif
                            @if (in_array($size, ['letter']))
                                <tr></tr>
                            @endif
                            <tr>
                                <td colspan="6"></td>
                                <td colspan="4" class="relative underline font-sm center bottom nowrap consolas">
                                    {{ $user?->name }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="font-xs nowrap consolas">
                                    DATE: <span class="uppercase">{{ now()->format('d M Y H:i') }}</span>
                                </td>
                                <td colspan="3"></td>
                                <td colspan="4" class="center nowrap font-xs arial top">
                                    {{ $user->position ?: $user?->employee?->designation ?? 'Officer-in-charge' }}
                                </td>
                            </tr>
                        @endif
                    </table>
                </div>
            @endforeach
        @endforeach
    @endforeach
@endsection

@push('head')
    <style>
        @media print {
            @page {
                margin: 0;
                size: {{
                    match($size) {
                        'folio' => '8.5in 13in',
                        default => $size,
                    }
                }};
            }
        }
    </style>
@endpush
