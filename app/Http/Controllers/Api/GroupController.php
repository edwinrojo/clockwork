<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Employee;
use App\Models\Group;
use App\Services\ActiveFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    private const FIELDS = [
        'groups.id',
        'groups.name',
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
            $q->where('groups.name', 'ilike', '%'.$value.'%');
        });
    }

    /**
     * Apply ordering to the query.
     */
    private function order(Builder|BelongsToMany $query): void
    {
        $query->orderBy('groups.name');
    }

    /**
     * Apply active/inactive filter to the query.
     */
    private function active(Builder|BelongsToMany $query, Request $request): void
    {
        $this->activeFilterService->applyActiveFilter($query, [
            'pivot' => true,
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
            $query = $employee->groups()
                ->select(self::FIELDS)
                ->withCount('employees');

            $this->active($query, $request);

            $this->filter($query, $request);

            $this->order($query);

            $paginate = min(max((int) $request->get('paginate', 100), 1), 1000);

            $groups = $query->paginate($paginate, pageName: 'page')->appends($request->query());

            return GroupResource::collection($groups);
        }

        $query = Group::query()
            ->select(self::FIELDS)
            ->withCount('employees');

        $this->filter($query, $request);

        $this->order($query);

        $paginate = min(max((int) $request->get('paginate', 100), 1), 1000);

        $groups = $query->paginate($paginate, pageName: 'page')->appends($request->query());

        return GroupResource::collection($groups);
    }

    /**
     * Display the specified resource.
     *
     * @return GroupResource
     */
    public function show(Request $request, Group $group, ?Employee $employee = null)
    {
        if ($employee) {
            $query = $employee->groups()
                ->where('groups.id', $group->id)
                ->select(self::FIELDS)
                ->withCount('employees');

            $this->active($query, $request);

            return new GroupResource($query->firstOrFail());
        }

        $group->loadCount('employees');

        return new GroupResource($group);
    }
}
