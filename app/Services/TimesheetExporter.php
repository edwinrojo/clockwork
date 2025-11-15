<?php

namespace App\Services;

use App\Actions\SignPdfAction;
use App\Helpers\NumberRangeCompressor;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webklex\PDFMerger\Facades\PDFMergerFacade;
use Webklex\PDFMerger\PDFMerger;
use ZipArchive;

use function Safe\file_get_contents;
use function Safe\tmpfile;

class TimesheetExporter implements Responsable
{
    private Collection|Employee $employee;

    private Carbon $month;

    private ?User $user = null;

    private array|bool|null $signature = null;

    private string $period = 'full';

    private array $dates = [];

    private string $format = 'csc';

    private string $size = 'folio';

    private int $transmittal = 0;

    private false|string|null $grouping = 'offices';

    private bool $individual = false;

    private bool $single = false;

    private array $misc = [];

    private bool $download = true;

    private bool $check = true;

    public function __construct(
        Collection|Employee|null $employee = null,
        Carbon|string|null $month = null,
        string $period = 'full',
    ) {
        if ($employee) {
            $this->employee($employee);
        }

        if ($month) {
            $this->month($month);
        }

        $this->period($period);
    }

    public function __invoke(
        Collection|Employee $employee,
        Carbon|string $month,
        string $period = 'full',
        array $dates = [],
        string $format = 'csc',
        string $size = 'folio',
        int $transmittal = 0,
        false|string|null $grouping = 'offices',
        array|bool|null $signature = null,
        array $misc = [],
    ): StreamedResponse {
        return $this->employee($employee)
            ->month($month)
            ->period($period)
            ->dates($dates)
            ->format($format)
            ->size($size)
            ->transmittal($transmittal)
            ->grouping($grouping)
            ->signature($signature)
            ->misc($misc)
            ->download();
    }

    public function user(?User $user = null): static
    {
        $this->user = $user;

        return $this;
    }

    public function signature(array|bool|null $signature = null): static
    {
        $this->signature = $signature;

        return $this;
    }

    public function employee(Collection|Employee $employee): static
    {
        if ($employee instanceof Collection) {
            $employee->ensure(Employee::class);
        }

        $this->employee = $employee;

        return $this;
    }

    public function month(Carbon|string $month): static
    {
        $this->month = is_string($month) ? Carbon::parse($month) : $month;

        return $this;
    }

    public function period(string $period = 'full'): static
    {
        if (! in_array(explode('|', $period)[0], ['full', '1st', '2nd', 'overtime', 'regular', 'dates', 'range'])) {
            throw new InvalidArgumentException('Unknown period: '.$period);
        }

        $this->period = $period;

        return $this;
    }

    public function dates(array $dates): static
    {
        $this->dates = collect($dates)
            ->filter(fn ($date) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
            ->toArray();

        return $this;
    }

    public function format(string $format = 'csc'): static
    {
        if (! in_array($format, ['default', 'csc', 'preformatted'])) {
            throw new InvalidArgumentException('Unknown format: '.$format);
        }

        $this->format = $format;

        return $this;
    }

    public function size(string $size = 'folio'): static
    {
        if (! in_array(mb_strtolower($size), ['a4', 'letter', 'folio', 'legal'])) {
            throw new InvalidArgumentException('Unknown size: '.$size);
        }

        $this->size = mb_strtolower($size);

        return $this;
    }

    public function individual(bool $individual = true): static
    {
        $this->individual = $individual;

        return $this;
    }

    public function transmittal(int $transmittal = 0): static
    {
        $this->transmittal = $transmittal;

        return $this;
    }

    public function grouping(false|string|null $grouping = 'offices'): static
    {
        $this->grouping = $grouping === '0' ? false : $grouping;

        return $this;
    }

    public function single(bool $single): static
    {
        $this->single = $single;

        return $this;
    }

    public function misc(array $misc): static
    {
        $this->misc = $misc;

        return $this;
    }

    public function check(bool $check = true): static
    {
        $this->check = $check;

        return $this;
    }

    public function skipChecks(): bool
    {
        return ! $this->check;
    }

    public function download(bool $download = true): BinaryFileResponse|StreamedResponse|array
    {
        if ($this->format === 'default' && in_array($this->period, ['regular', 'overtime'])) {
            throw new InvalidArgumentException('Default format is not supported for regular and overtime period');
        }

        $this->download = $download;

        @[$period, $range] = explode('|', $this->period, 2);

        @[$from, $to] = explode('-', $range ?? '', 2);

        if ($this->format === 'default') {
            $timelogs = function ($query) use ($period, $from, $to) {
                $query->month($this->month->startOfMonth());

                match ($period) {
                    '1st' => $query->firstHalf(),
                    '2nd' => $query->secondHalf(),
                    'dates' => $query->customDates($this->dates),
                    'range' => $query->customRange($from, $to),
                    default => $query,
                };
            };

            $this->employee->load([
                'timelogs' => $timelogs,
                'scanners' => fn ($query) => $query->reorder()->orderBy('priority', 'desc')->orderBy('name'),
                'timelogs.scanner',
            ]);

            if ($this->employee instanceof Collection) {
                $this->employee = $this->employee->sortBy('full_name');
            }

            return match ($this->individual) {
                true => $this->exportAsZip(),
                default => $this->exportAsPdf(),
            };
        }

        if ($this->format === 'preformatted') {
            [$from, $to] = match ($period) {
                'dates' => null,
                'range' => [$from, $to],
                default => [
                    match ($period) {
                        '2nd' => 16,
                        default => 1,
                    },
                    match ($period) {
                        '1st' => 15,
                        default => $this->month->daysInMonth,
                    },
                ],
            };

            $this->employee->load([
                'currentDeployment.supervisor',
                'currentDeployment.office.head',
                'timelogs.scanner',
                'timelogs' => function ($query) use ($period, $from, $to) {
                    [$year, $month] = explode('-', $this->month);

                    $query->where(function ($query) use ($year, $month, $period, $from, $to) {
                        $query->whereYear('time', $year)->whereMonth('time', $month);

                        match ($period) {
                            '1st' => $query->firstHalf(),
                            '2nd' => $query->secondHalf(),
                            'dates' => $query->customDates($this->dates),
                            'range' => $query->customRange($from, $to),
                            default => $query,
                        };
                    });

                    $query->orWhere(function ($query) use ($year, $month, $from, $to) {
                        $query->whereDate('time', Carbon::create($year, $month, $from)->subDay());

                        $query->orWhereDate('time', Carbon::create($year, $month, $to)->addDay());
                    });

                    $query->reorder()->orderBy('time');
                },
            ]);

            return match ($this->individual) {
                true => $this->exportAsZip(),
                default => $this->exportAsPdf(),
            };
        }

        $id = $this->employee instanceof Collection ? $this->employee->pluck('id')->toArray() : [$this->employee->id];

        $timesheets = Timesheet::query()
            ->whereIn('employee_id', $id)
            ->whereDate('month', $this->month->startOfMonth())
            ->where('span', 'full')
            ->when($period === '1st', fn ($query) => $query->with('firstHalf'))
            ->when($period === '2nd', fn ($query) => $query->with('secondHalf'))
            ->when($period === 'regular', fn ($query) => $query->with('regularDays'))
            ->when($period === 'overtime', fn ($query) => $query->with('overtimeWork'))
            ->with(['employee:id,name,status', 'annotations'])
            ->orderBy(Employee::select('full_name')->whereColumn('employees.id', 'timesheets.employee_id')->limit(1))
            ->lazy();

        $timesheets = match ($period) {
            '1st' => $timesheets->map->setFirstHalf(),
            '2nd' => $timesheets->map->setSecondHalf(),
            'overtime' => $timesheets->map->setOvertimeWork(),
            'regular' => $timesheets->map->setRegularDays(),
            'dates' => $timesheets->map->setCustomDates($this->dates),
            'range' => $timesheets->map->setCustomRange($from, $to),
            default => $timesheets,
        };

        $timesheets = $timesheets->map->setTemporary();

        if ($this->transmittal > 0 && $this->grouping !== false) { // grouping only available to office
            $timesheets = $timesheets->groupBy(fn ($timesheet) => $timesheet->employee->offices->pluck('code')->toArray())->flatten();
        }

        return match ($this->individual) {
            true => $this->exportAsZip($timesheets),
            default => $this->exportAsPdf($timesheets),
        };
    }

    protected function exportAsZip(LazyCollection|Collection|null $exportable = null): BinaryFileResponse|array
    {
        $name = $this->filename().'.zip';

        $headers = ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="'.$name.'"'];

        $temp = stream_get_meta_data(tmpfile())['uri'];

        $zip = new ZipArchive;

        $zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->setCompressionIndex(-1, ZipArchive::CM_STORE);

        match ($exportable) {
            null => $this->employee->each(function ($employee) use ($zip) {
                $content = match ($this->signature === true ?: @$this->signature['digital']) {
                    true => $this->signed([$employee]),
                    default => $this->pdf([$employee]),
                };

                $zip->addFromString($employee->name.'.pdf', $content);
            }),

            default => $exportable->each(function ($timesheet) use ($zip) {
                $content = match ($this->signature === true ?: @$this->signature['digital']) {
                    true => $this->signed([$timesheet]),
                    default => $this->pdf([$timesheet]),
                };

                $zip->addFromString($timesheet->employee->name.'.pdf', $content);
            })
        };

        $zip->close();

        if ($this->download) {
            return response()->download($temp, $name, $headers)->deleteFileAfterSend();
        }

        try {
            return [
                'filename' => $name,
                'content' => file_get_contents($temp),
            ];
        } finally {
            if (file_exists($temp)) {
                unlink($temp);
            }
        }
    }

    protected function exportAsPdf(LazyCollection|Collection|null $exportable = null): StreamedResponse|array
    {
        $name = $this->filename().'.pdf';

        $headers = ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'"'];

        $downloadable = match ($this->signature === true ?: @$this->signature['digital']) {
            true => $this->signed($exportable),
            default => $this->pdf($exportable),
        };

        if ($this->download) {
            return response()->streamDownload(fn () => print ($downloadable), $name, $headers);
        }

        return [
            'filename' => $name,
            'content' => $downloadable,
        ];
    }

    protected function pdf(?iterable $exportable, bool $base64 = true): PdfBuilder|PDFMerger|string
    {
        @[$period, $range] = explode('|', $this->period, 2);

        if ($period === 'range') {
            [$from, $to] = explode('-', $range, 2);
        } elseif ($period !== 'dates') {
            $from = match ($period) {
                '2nd' => 16,
                default => 1,
            };

            $to = match ($period) {
                '1st' => 15,
                default => $this->month->daysInMonth,
            };
        }

        if ($this->format === 'csc') {
            $args = [
                'size' => $this->size,
                'timesheets' => $exportable,
            ];
        } elseif ($this->format === 'preformatted') {
            $args = [
                'size' => $this->size,
                'month' => $this->month,
                'from' => $period !== 'dates' ? $from : null,
                'to' => $period !== 'dates' ? $to : null,
                'dates' => $period === 'dates' ? $this->dates : null,
                'period' => $period,
                'employees' => match ($exportable) {
                    null => match (get_class($this->employee)) {
                        Collection::class, EloquentCollection::class, LazyCollection::class => $this->employee,
                        default => EloquentCollection::make([$this->employee])
                    },
                    default => match (is_array($exportable)) {
                        true => EloquentCollection::make($exportable),
                        default => $exportable,
                    },
                },
            ];
        } else {
            $args = [
                'size' => $this->size,
                'signature' => $this->signature,
                'month' => $this->month,
                'from' => $period !== 'dates' ? $from : null,
                'to' => $period !== 'dates' ? $to : null,
                'dates' => $period === 'dates' ? $this->dates : null,
                'period' => $period,
                'employees' => match ($exportable) {
                    null => match (get_class($this->employee)) {
                        Collection::class, EloquentCollection::class, LazyCollection::class => $this->employee,
                        default => EloquentCollection::make([$this->employee])
                    },
                    default => match (is_array($exportable)) {
                        true => EloquentCollection::make($exportable),
                        default => $exportable,
                    },
                },
            ];
        }

        $view = match ($this->format) {
            'csc' => 'print.csc',
            'preformatted' => 'print.preformatted',
            default => 'print.default',
        };

        $export = Pdf::view($view, [
            ...$args,
            'user' => $this->user ?? Auth::user(),
            'misc' => $this->misc,
            'single' => $this->single,
            'signature' => $this->signature === true ?: @$this->signature['electronic'],
            'signed' => $this->signature === true ?: @$this->signature['digital'],
        ])
            ->withBrowsershot(fn (Browsershot $browsershot) => $browsershot->noSandbox()->setOption('args', ['--disable-web-security']));

        match ($this->size) {
            'folio' => $export->paperSize(8.5, 13, 'in'),
            default => $export->format($this->size),
        };

        if ($this->transmittal) {
            $transmittal = Pdf::view('print.transmittal.csc-default', [
                ...$args,
                'format' => $this->format,
                'copies' => $this->transmittal,
                'user' => $this->user ?? Auth::user(),
                'signed' => $this->signature === true ?: @$this->signature['digital'],
                'month' => $this->month,
                'from' => $args['from'] ?? $from ?? null,
                'to' => $args['to'] ?? $to ?? null,
                'dates' => $args['dates'] ?? $this->dates,
                'period' => $args['period'] ?? $period,
                'signature' => $this->signature === true ?: @$this->signature['electronic'],
                'signed' => $this->signature === true ?: @$this->signature['digital'],
                'employees' => $this->format === 'csc'
                    ? EloquentCollection::make(collect($exportable)->pluck('employee'))
                    : $args['employees'],
                'misc' => $this->misc,
            ])
                ->withBrowsershot(fn (Browsershot $browsershot) => $browsershot->noSandbox()->setOption('args', ['--disable-web-security']));

            match ($this->size) {
                'folio' => $transmittal->paperSize(8.5, 13, 'in'),
                default => $transmittal->format($this->size),
            };

            $merger = PDFMergerFacade::init();

            if (! is_dir(storage_path('tmp'))) {
                mkdir(storage_path('tmp'));
            }

            $merger->addString(base64_decode($transmittal->base64()), 'all')->addString(base64_decode($export->base64()), 'all');

            $merger->merge();

            return $base64 ? $merger->output() : $merger;
        }

        return $base64 ? base64_decode($export->base64()) : $export;
    }

    protected function signed(?iterable $timesheets): string
    {
        $export = $this->pdf($timesheets, false);

        $name = $this->filename().'.pdf';

        $path = sys_get_temp_dir()."/$name";

        $export->save($path);

        (new SignPdfAction)
            ($this->user, $path, null, 'employee-field', SignPdfAction::FOLIO_TIMESHEET_EMPLOYEE_COORDINATES);

        try {
            return file_get_contents($path);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    protected function filename(): string
    {
        $prefix = 'Timesheets '.$this->month->format('Y-m ').match ($this->period) {
            'full' => '',
            '1st' => '(First half)',
            '2nd' => '(Second half)',
            'overtime' => '(Overtime Work)',
            'regular' => '(Regular Days)',
            'dates' => (new NumberRangeCompressor)(collect($this->dates)->map(fn ($date) => Carbon::parse($date)->format('j'))->values()->toArray()),
            default => '('.str($this->period)->replace('range|', '').')',
        };

        $name = $this->employee instanceof Collection ? substr($this->employee->pluck('last_name')->sort()->join(','), 0, 60) : $this->employee->name;

        return "$prefix ($name)";
    }

    public function toResponse($request): BinaryFileResponse|StreamedResponse
    {
        return $this->download();
    }

    public function id()
    {
        return hash('sha256', json_encode($this->args()));
    }

    public function args()
    {
        return [
            'employee' => $this->employee,
            'month' => $this->month,
            'user' => $this->user,
            'signature' => $this->signature,
            'period' => $this->period,
            'dates' => $this->dates,
            'format' => $this->format,
            'size' => $this->size,
            'transmittal' => $this->transmittal,
            'grouping' => $this->grouping,
            'individual' => $this->individual,
            'single' => $this->single,
            'misc' => $this->misc,
        ];
    }
}
