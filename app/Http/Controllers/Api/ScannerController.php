<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScannerResource;
use App\Models\Employee;
use App\Models\Scanner;
use App\Services\ActiveFilterService;
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
        private ActiveFilterService $activeFilterService
    ) {}

    /**
     * Apply search filters to the query.
     */
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

    /**
     * Apply ordering to the query.
     */
    private function order(Builder|BelongsToMany $query): void
    {
        $query->orderBy('scanners.priority')->orderBy('scanners.name');
    }

    /**
     * Apply enrollment active/inactive filter to the query.
     */
    private function enrollment(Builder|BelongsToMany $query, Request $request): void
    {
        $this->activeFilterService->applyActiveFilter($query, [
            'pivot' => true,
            'active' => filter_var($request->get('active', true), FILTER_VALIDATE_BOOLEAN),
            'inactive' => filter_var($request->get('inactive', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * Apply scanner active/inactive filter to the query.
     */
    private function scanner(Builder $query, Request $request): void
    {
        $this->activeFilterService->applyActiveFilter($query, [
            'table' => 'scanners',
            'active' => filter_var($request->get('active', true), FILTER_VALIDATE_BOOLEAN),
            'inactive' => filter_var($request->get('inactive', false), FILTER_VALIDATE_BOOLEAN),
        ]);
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
                ->select(self::FIELDS)
                ->withCount('employees');

            $this->enrollment($query, $request);

            $this->filter($query, $request);

            $this->order($query);

            $paginate = min(max((int) $request->get('paginate', 100), 1), 1000);

            $scanners = $query->paginate($paginate, pageName: 'page')->appends($request->query());

            return ScannerResource::collection($scanners);
        }

        $query = Scanner::query()
            ->withCount('employees');

        $this->filter($query, $request);

        $this->scanner($query, $request);

        $this->order($query);

        $paginate = min(max((int) $request->get('paginate', 100), 1), 1000);

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
                ->select(self::FIELDS)
                ->withCount('employees');

            $this->enrollment($query, $request);

            return new ScannerResource($query->firstOrFail());
        }

        $scanner->loadCount('employees');

        return new ScannerResource($scanner);
    }
}
