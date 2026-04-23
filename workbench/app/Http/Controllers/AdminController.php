<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Foundry;
use Foundry\Models\Module;
use Foundry\Notifications\NewAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class AdminController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Foundry::$adminModel, 'admin', [
            'except' => ['show', 'update', 'destroy', 'restore'],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $admin = Foundry::$adminModel::with('lastLogin', 'groups');

        if ($request->has('filter') && ! empty($request->filter)) {
            $admin->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'like', "%{$request->filter}%");
            $admin->orWhere('email', 'like', "%{$request->filter}%");
        }

        if ($request->has('group') && ! empty($request->group)) {
            $admin->whereHas('groups', function ($query) use ($request) {
                $query->where('id', $request->group);
            });
        }

        if ($request->boolean('active')) {
            $admin->onlyActive();
        }

        if ($request->boolean('hideCurrent')) {
            $admin->excludeCurrent();
        }

        if ($request->boolean('deleted')) {
            $admin->onlyTrashed();
        }

        $admin = $admin->sortBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?? 15);

        return new ResourceCollection($admin);
    }

    /**
     * Display a options listing of the resource.
     */
    public function options(Request $request)
    {
        $request->merge([
            'option' => true,
        ]);

        return $this->index($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:admins',
            'password' => 'required|min:6|confirmed',
        ];

        $request->validate($rules);

        $password = $request->filled('password') ? $request->password : fake()->regexify('/^IN@\d{3}[A-Z]{4}$/');

        $request->merge([
            'password' => bcrypt($password),
        ]);

        $admin = Foundry::$adminModel::create($request->input());

        $admin->syncGroups(collect($request->groups));

        $admin->syncPermissions(collect($request->permissions));

        $admin->notify(new NewAdminNotification($admin, $password));

        return response()->json([
            'data' => $admin->load('groups', 'permissions'),
            'message' => __('Staff account has been created successfully!'),
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $admin = Foundry::$adminModel::withTrashed()->findOrFail($id);

        $this->authorize('view', [$admin]);

        $admin = $admin->load([
            'permissions',
            'groups',
            'lastLogin',
        ]);

        return response()->json($this->toArray($admin), 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $admin = Foundry::$adminModel::withTrashed()->findOrFail($id);

        $this->authorize('update', [$admin]);

        // Set rules
        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'email|unique:admins,email,'.$admin->id,
            'password' => 'min:6|confirmed',
        ];

        // Validate those rules
        $request->validate($rules);

        if ($request->filled('password')) {
            $request->merge([
                'password' => bcrypt($request->password),
            ]);
        }

        if ($admin->id == user()->id) {
            $admin->update($request->except(['is_active', 'is_super_admin']));
        } else {
            $admin->update($request->input());
        }

        $admin->syncGroups(collect($request->groups));

        $admin->syncPermissions(collect($request->permissions));

        return response()->json([
            'data' => $this->toArray($admin->load('groups', 'permissions')),
            'message' => __('Staff account has been updated successfully!'),
        ], 200);
    }

    /**
     * Display a listing of the permission.
     */
    public function modules(Request $request)
    {
        $modules = Module::with('permissions')->get()->map(function ($item) {
            $item->label = __($item->name);

            return $item;
        });

        return response()->json($modules, 200);
    }

    /**
     * Send reset password request to specified resource from storage.
     */
    public function resetPasswordRequest(Request $request, $id)
    {
        $admin = Foundry::$adminModel::findOrFail($id);

        $status = Password::sendResetLink([
            'email' => $admin->email,
        ]);

        return response()->json([
            'status' => $status,
            'message' => __('Password reset link sent successfully!'),
        ], 200);
    }

    /**
     * Change admin of specified resource from storage.
     */
    public function changeAdmin(Request $request, $id)
    {
        $admin = Foundry::$adminModel::findOrFail($id);

        $this->authorize('update', [$admin]);

        if ($admin->id == user()->id) {
            return response()->json([
                'message' => __('Staff can not update permissions of his/her self account.'),
            ], 403);
        }

        $admin->update([
            'is_super_admin' => ! $admin->is_super_admin,
        ]);

        $type = $admin->is_super_admin ? 'marked' : 'unmarked';

        return response()->json([
            'message' => __('Staff account :type as admin successfully!', ['type' => __($type)]),
        ], 200);
    }

    /**
     * Change active of specified resource from storage.
     */
    public function changeActive(Request $request, $id)
    {
        $admin = Foundry::$adminModel::findOrFail($id);

        $this->authorize('update', [$admin]);

        if ($admin->id == user()->id) {
            return response()->json([
                'message' => __('Reply has been created successfully!'),
            ], 403);
        }

        $admin->update([
            'is_active' => ! $admin->is_active,
        ]);

        $type = ! $admin->is_active ? 'active' : 'deactive';

        return response()->json([
            'message' => __('Staff account marked as :type successfully!', ['type' => __($type)]),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $admin = Foundry::$adminModel::withTrashed()->findOrFail($id);

        $this->authorize('delete', [$admin]);

        if ($admin->trashed()) {
            $admin->forceDelete();
        } else {
            $admin->delete();
        }

        return response()->json([
            'message' => __('Staff account has been deleted successfully!'),
        ], 200);
    }

    /**
     * Remove the selected resources from storage.
     */
    public function destroySelected(Request $request)
    {
        $this->authorize('deleteAny', [Foundry::$adminModel]);

        $request->validate([
            'items' => 'required|array',
        ]);

        $query = Foundry::$adminModel::withTrashed()->whereIn('id', $request->items);

        if ($request->boolean('force')) {
            $query->forceDelete();
        } else {
            $query->delete();
        }

        return response()->json([
            'message' => __('Selected staff accounts have been deleted successfully!'),
        ], 200);
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore($id)
    {
        $admin = Foundry::$adminModel::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', [$admin]);

        $admin->restore();

        return response()->json([
            'message' => __('Staff account has been restored successfully!'),
        ], 200);
    }

    /**
     * Restore the selected resources from storage.
     */
    public function restoreSelected(Request $request)
    {
        $this->authorize('restoreAny', [Foundry::$adminModel]);

        $request->validate([
            'items' => 'required|array',
        ]);

        Foundry::$adminModel::onlyTrashed()->whereIn('id', $request->items)->restore();

        return response()->json([
            'message' => __('Selected staff accounts have been restored successfully!'),
        ], 200);
    }

    private function toArray($admin)
    {
        $data = $admin->toArray();

        $data['permissions'] = $admin->permissions->map(function ($permission) {
            return [
                'id' => $permission->id,
                'access' => $permission->pivot->access,
            ];
        });

        $data['groupPermissions'] = $admin->getPermissionsViaGroups()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'access' => $permission->pivot->access,
            ];
        });

        return $data;
    }
}
