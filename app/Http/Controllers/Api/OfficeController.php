<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfficeResource;
use App\Models\Employee;
use App\Models\Office;
use App\Services\DeploymentFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    private const FIELDS = [
        'offices.id',
        'offices.name',
        'offices.code',
        'offices.logo',
    ];

    public function __construct(
        private DeploymentFilterService $deploymentFilterService
    ) {}

    private function filter(Builder|BelongsToMany $query, Request $request): void
    {
        $query->when($request->get('search'), function (Builder $q, string $value): void {
            $q->whereAny([
                'offices.name',
                'offices.code',
            ], 'ilike', '%'.$value.'%');
        });
    }

    private function order(Builder|BelongsToMany $query): void
    {
        $query->orderBy('offices.code');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, ?Employee $employee = null)
    {
        if ($employee) {
            $query = $employee->offices()
                ->select(self::FIELDS);

            $active = filter_var($request->get('active', true), FILTER_VALIDATE_BOOLEAN);
            $inactive = filter_var($request->get('inactive', false), FILTER_VALIDATE_BOOLEAN);
            $this->deploymentFilterService->applyDeploymentActiveFilter($query, $active, $inactive);

            $this->filter($query, $request);

            $this->order($query);

            $paginate = min(max((int) $request->get('paginate', 100), 1), 1000);

            $offices = $query->paginate($paginate, pageName: 'page')->appends($request->query());

            return OfficeResource::collection($offices);
        }

        $query = Office::query()
            ->select(self::FIELDS);

        $this->filter($query, $request);

        $this->order($query);

        $paginate = min(max((int) $request->get('paginate', 15), 1), 100);

        $offices = $query->paginate($paginate, pageName: 'page')->appends($request->query());

        return OfficeResource::collection($offices);
    }

    /**
     * Display the specified resource.
     *
     * @return OfficeResource
     */
    public function show(Request $request, Office $office, ?Employee $employee = null)
    {
        if ($employee) {
            $query = $employee->offices()
                ->where('offices.id', $office->id)
                ->select(self::FIELDS);

            $active = filter_var($request->get('active', true), FILTER_VALIDATE_BOOLEAN);
            $inactive = filter_var($request->get('inactive', false), FILTER_VALIDATE_BOOLEAN);
            $this->deploymentFilterService->applyDeploymentActiveFilter($query, $active, $inactive);

            return new OfficeResource($query->firstOrFail());
        }

        return new OfficeResource($office);
    }
}
