<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScannerResource;
use App\Models\Employee;
use App\Models\Scanner;
use App\Services\EnrollmentFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

class ScannerController extends Controller
{
    private const FIELDS = [
        'scanners.id',
        'scanners.name',
        'scanners.uid',
        'scanners.priority',
        'scanners.active',
        'scanners.synced_at',
    ];

    public function __construct(
        private EnrollmentFilterService $enrollmentFilterService
    ) {}

    private function filter(Builder|BelongsToMany $query, Request $request): void
    {
        $query->when($request->get('search'), function (Builder $q, string $value): void {
            $q->whereAny([
                'scanners.name',
                'scanners.uid',
                'scanners.host',
            ], 'ilike', '%'.$value.'%');
        });
    }

    private function order(Builder|BelongsToMany $query): void
    {
        $query->orderBy('scanners.priority')->orderBy('scanners.name');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, ?Employee $employee = null)
    {
        if ($employee) {
            $query = $employee->scanners()
                ->select(self::FIELDS);

            $active = filter_var($request->get('active', true), FILTER_VALIDATE_BOOLEAN);
            $inactive = filter_var($request->get('inactive', false), FILTER_VALIDATE_BOOLEAN);
            $this->enrollmentFilterService->applyEnrollmentActiveFilter($query, $active, $inactive);

            $this->filter($query, $request);

            $this->order($query);

            $paginate = min(max((int) $request->get('paginate', 15), 1), 100);

            $scanners = $query->paginate($paginate, pageName: 'page')->appends($request->query());

            return ScannerResource::collection($scanners);
        }

        $query = Scanner::query();

        $this->filter($query, $request);

        $active = filter_var($request->get('active', true), FILTER_VALIDATE_BOOLEAN);
        $inactive = filter_var($request->get('inactive', false), FILTER_VALIDATE_BOOLEAN);
        $this->enrollmentFilterService->applyScannerActiveFilter($query, $active, $inactive);

        $this->order($query);

        $paginate = min(max((int) $request->get('paginate', 15), 1), 100);

        $scanners = $query->paginate($paginate, pageName: 'page')->appends($request->query());

        return ScannerResource::collection($scanners);
    }

    /**
     * Display the specified resource.
     *
     * @return ScannerResource
     */
    public function show(Request $request, ?Employee $employee = null, ?Scanner $scanner = null)
    {
        if (! $scanner) {
            $scanner = Scanner::findOrFail($request->route('scanner'));
        }

        if ($employee) {
            $query = $employee->scanners()
                ->where('scanners.id', $scanner->id)
                ->select(self::FIELDS);

            $active = filter_var($request->get('active', true), FILTER_VALIDATE_BOOLEAN);
            $inactive = filter_var($request->get('inactive', false), FILTER_VALIDATE_BOOLEAN);
            $this->enrollmentFilterService->applyEnrollmentActiveFilter($query, $active, $inactive);

            return new ScannerResource($query->firstOrFail());
        }

        return new ScannerResource($scanner);
    }
}
