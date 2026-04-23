<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Group::class, 'group', [
            'except' => ['show'],
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $group = Group::query();

        if ($request->has('filter') && ! empty($request->filter)) {
            $group->where('name', 'like', "%{$request->filter}%");
        }

        if ($request->input('deleted') ? $request->boolean('deleted') : false) {
            $group->onlyTrashed();
        }

        $group = $group->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?? 15);

        return new ResourceCollection($group);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|unique:groups',
        ];

        $request->validate($rules);

        $group = Group::create($request->input());

        $permissions = collect($request->permissions)
            ->filter(function ($permission) {
                return ! is_null($permission['access']);
            })
            ->mapWithKeys(function ($permission) {
                return [$permission['id'] => [
                    'access' => $permission['access'],
                ]];
            });
        $group->permissions()->sync($permissions);

        return response()->json([
            'data' => $group->load('permissions'),
            'message' => __('Group has been created successfully!'),
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show($group)
    {
        $group = Group::withTrashed()->findOrFail($group);

        Gate::authorize('view', $group);

        return response()->json($this->toArray($group), 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(Request $request, Group $group)
    {
        // Set rules
        $rules = [
            'name' => 'required|unique:groups,name,'.$group->id,
        ];

        // Validate those rules
        $request->validate($rules);

        $group->update($request->input());

        $permissions = collect($request->permissions)
            ->filter(function ($permission) {
                return ! is_null($permission['access']);
            })
            ->mapWithKeys(function ($permission) {
                return [$permission['id'] => [
                    'access' => $permission['access'],
                ]];
            });
        $group->permissions()->sync($permissions);

        return response()->json([
            'data' => $group->load('permissions'),
            'message' => __('Group has been updated successfully!'),
        ], 200);
    }

    public function destroy(Request $request, Group $group)
    {
        if ($request->boolean('force')) {
            $group->forceDelete();
            $message = __('Group has been permanently deleted successfully!');
        } else {
            $group->delete();
            $message = __('Group has been deleted successfully!');
        }

        return response()->json([
            'message' => $message,
        ], 200);
    }

    public function destroySelected(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
        ]);

        $query = Group::whereIn('id', $request->items);

        if ($request->boolean('force')) {
            $query->forceDelete();
            $message = __('Groups has been permanently deleted successfully!');
        } else {
            $query->delete();
            $message = __('Groups has been deleted successfully!');
        }

        return response()->json([
            'message' => $message,
        ], 200);
    }

    public function restore($group)
    {
        $group = Group::onlyTrashed()->findOrFail($group);
        $group->restore();

        return response()->json([
            'message' => __('Group has been restored successfully!'),
        ], 200);
    }

    public function restoreSelected(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
        ]);

        Group::onlyTrashed()
            ->whereIn('id', $request->items)
            ->restore();

        return response()->json([
            'message' => __('Groups has been restored successfully!'),
        ], 200);
    }

    private function toArray(Group $group)
    {
        $data = $group->toArray();

        $data['permissions'] = $group->permissions->map(function ($permission) {
            return [
                'id' => $permission->id,
                'access' => $permission->pivot->access,
            ];
        });

        return $data;
    }
}
