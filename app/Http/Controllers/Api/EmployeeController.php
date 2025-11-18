<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Office;
use App\Models\Scanner;
use App\Services\ActiveFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    private const FIELDS = [
        'employees.id',
        'employees.first_name',
        'employees.middle_name',
        'employees.last_name',
        'employees.prefix_name',
        'employees.suffix_name',
        'employees.qualifier_name',
        'employees.name',
        'employees.full_name',
        'employees.status',
        'employees.substatus',
        'employees.email',
        'employees.birthdate',
        'employees.sex',
        'employees.uid',
    ];

    public function __construct(
        private ActiveFilterService $activeFilterService
    ) {}

    /**
     * Apply search filters to the query.
     */
    private function filter(Builder|BelongsToMany $query, Request $request): void
    {
        $query->when($request->get('first_name'), function (Builder $q, string $value): void {
            $q->where('employees.first_name', 'ilike', '%'.$value.'%');
        });

        $query->when($request->get('middle_name'), function (Builder $q, string $value): void {
            $q->where('employees.middle_name', 'ilike', '%'.$value.'%');
        });

        $query->when($request->get('last_name'), function (Builder $q, string $value): void {
            $q->where('employees.last_name', 'ilike', '%'.$value.'%');
        });

        $query->when($request->get('qualifier_name'), function (Builder $q, string $value): void {
            $q->where('employees.qualifier_name', 'ilike', '%'.$value.'%');
        });

        $query->when($request->get('search'), function (Builder $q, string $value): void {
            foreach (array_filter(preg_split('/\s+/', trim($value))) as $word) {
                $q->whereAny([
                    'employees.first_name',
                    'employees.middle_name',
                    'employees.last_name',
                    'employees.qualifier_name',
                    'employees.prefix_name',
                    'employees.suffix_name',
                    'employees.name',
                    'employees.full_name',
                ], 'ilike', '%'.$word.'%');
            }
        });
    }

    /**
     * Apply ordering to the query.
     */
    private function order(Builder|BelongsToMany $query): void
    {
        $query->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('employees.middle_name')
            ->orderBy('employees.suffix_name');
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
     * Apply deployment active/inactive filter to the query.
     */
    private function deployment(Builder|BelongsToMany $query, Request $request): void
    {
        $this->activeFilterService->applyActiveFilter($query, [
            'pivot' => true,
            'active' => filter_var($request->get('active', true), FILTER_VALIDATE_BOOLEAN),
            'inactive' => filter_var($request->get('inactive', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * Apply member active/inactive filter to the query.
     */
    private function member(Builder|BelongsToMany $query, Request $request): void
    {
        $this->activeFilterService->applyActiveFilter($query, [
            'pivot' => true,
            'active' => filter_var($request->get('active', true), FILTER_VALIDATE_BOOLEAN),
            'inactive' => filter_var($request->get('inactive', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * Display a listing of employees.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, ?Scanner $scanner = null, ?Office $office = null, ?Group $group = null)
    {
        if ($group) {
            $query = $group->employees()->select(self::FIELDS);

            $this->member($query, $request);

            $this->filter($query, $request);

            $this->order($query);

            $paginate = min(max((int) $request->get('paginate', 100), 1), 1000);

            $employees = $query->paginate($paginate, pageName: 'page')->appends($request->query());

            return EmployeeResource::collection($employees);
        }

        if ($office) {
            $query = $office->employees()->select(self::FIELDS);

            $this->deployment($query, $request);

            $this->filter($query, $request);

            $this->order($query);

            $paginate = min(max((int) $request->get('paginate', 100), 1), 1000);

            $employees = $query->paginate($paginate, pageName: 'page')->appends($request->query());

            return EmployeeResource::collection($employees);
        }

        if ($scanner) {
            $query = $scanner->employees()->select(self::FIELDS);

            $this->enrollment($query, $request);

            $this->filter($query, $request);

            $this->order($query);

            $paginate = min(max((int) $request->get('paginate', 100), 1), 1000);

            $employees = $query->paginate($paginate, pageName: 'page')->appends($request->query());

            return EmployeeResource::collection($employees);
        }

        $query = Employee::query()->select(self::FIELDS);

        $this->filter($query, $request);

        $this->order($query);

        $paginate = min(max((int) $request->get('paginate', 100), 1), 1000);

        $employees = $query->paginate($paginate, pageName: 'page')->appends($request->query());

        return EmployeeResource::collection($employees);
    }

    /**
     * Display the specified employee.
     *
     * @return EmployeeResource
     */
    public function show(Request $request, Employee $employee, ?Scanner $scanner = null, ?Office $office = null, ?Group $group = null)
    {
        if ($group) {
            $query = $group->employees()
                ->where('employees.id', $employee->id)
                ->select(self::FIELDS);

            $this->member($query, $request);

            return new EmployeeResource($query->firstOrFail());
        }

        if ($office) {
            $query = $office->employees()
                ->where('employees.id', $employee->id)
                ->select(self::FIELDS);

            $this->deployment($query, $request);

            return new EmployeeResource($query->firstOrFail());
        }

        if ($scanner) {
            $query = $scanner->employees()
                ->where('employees.id', $employee->id)
                ->select(self::FIELDS);

            $this->enrollment($query, $request);

            return new EmployeeResource($query->firstOrFail());
        }

        $employee->load(['scanners', 'offices', 'groups']);

        return new EmployeeResource($employee);
    }
}
