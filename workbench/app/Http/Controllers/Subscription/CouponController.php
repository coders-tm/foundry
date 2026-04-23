<?php

namespace Workbench\App\Http\Controllers\Subscription;

use Foundry\Http\Resources\Coupon\PlanCollection;
use Foundry\Http\Resources\CouponResource;
use Foundry\Models\Coupon;
use Foundry\Models\Subscription\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Http\Controllers\Controller;

class CouponController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Coupon::class, 'coupon', [
            'except' => ['show'],
        ]);
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        if ($request->boolean('force')) {
            $coupon->forceDelete();
            $message = __('Coupon has been permanently deleted successfully!');
        } else {
            $coupon->delete();
            $message = __('Coupon has been deleted successfully!');
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

        $query = Coupon::whereIn('id', $request->items);

        if ($request->boolean('force')) {
            $query->forceDelete();
            $message = __('Coupons has been permanently deleted successfully!');
        } else {
            $query->delete();
            $message = __('Coupons has been deleted successfully!');
        }

        return response()->json([
            'message' => $message,
        ], 200);
    }

    public function restore($coupon)
    {
        $coupon = Coupon::onlyTrashed()->findOrFail($coupon);
        $coupon->restore();

        return response()->json([
            'message' => __('Coupon has been restored successfully!'),
        ], 200);
    }

    public function restoreSelected(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
        ]);

        Coupon::onlyTrashed()
            ->whereIn('id', $request->items)
            ->restore();

        return response()->json([
            'message' => __('Coupons has been restored successfully!'),
        ], 200);
    }

    public function index(Request $request)
    {
        $coupon = Coupon::query();

        if ($request->filled('filter')) {
            $coupon->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->filter}%")
                    ->orWhere('promotion_code', 'like', "%{$request->filter}%");
            });
        }

        if ($request->boolean('active')) {
            $coupon->onlyActive();
        }

        if ($request->boolean('deleted')) {
            $coupon->onlyTrashed();
        }

        $coupon = $coupon->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?? 15);

        return new ResourceCollection($coupon);
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required',
            'promotion_code' => 'required|unique:coupons,promotion_code',
            'duration' => 'required',
            'duration_in_months' => 'required_if:duration,repeating',
            'amount_off' => 'required_if:percent_off,null',
            'percent_off' => 'required_if:amount_off,null',
        ];

        // Validate those rules
        $request->validate($rules);

        // create the coupon
        $coupon = Coupon::create($request->input());

        $coupon = $coupon->syncPlans($request->plans ?? []);

        return response()->json([
            'data' => $coupon->fresh(['plans', 'logs']),
            'message' => __('Coupon has been created successfully!'),
        ], 200);
    }

    public function show($coupon)
    {
        $coupon = Coupon::withTrashed()->findOrFail($coupon);

        Gate::authorize('view', $coupon);

        return response()->json(new CouponResource($coupon->load('plans', 'logs')), 200);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $rules = [
            'name' => 'required',
            'promotion_code' => 'required|unique:coupons,promotion_code,'.$coupon->id,
            'duration' => 'required',
            'duration_in_months' => 'required_if:duration,repeating',
            'amount_off' => 'required_if:percent_off,null',
            'percent_off' => 'required_if:amount_off,null',
        ];

        // Validate those rules
        $request->validate($rules);

        // update the coupon
        $coupon->update($request->input());

        $coupon = $coupon->syncPlans($request->plans ?? []);

        return response()->json([
            'data' => $coupon->fresh(['plans', 'logs']),
            'message' => __('Coupon has been updated successfully!'),
        ], 200);
    }

    public function changeActive(Request $request, Coupon $coupon)
    {
        $coupon->update([
            'active' => ! $coupon->active,
        ]);

        $type = $coupon->active ? 'active' : 'deactive';

        return response()->json([
            'message' => __('Coupon marked as :type successfully!', ['type' => __($type)]),
        ], 200);
    }

    /**
     * Create logs for specified resource from storage.
     */
    public function logs(Request $request, Coupon $coupon)
    {
        $request->validate([
            'message' => 'required',
        ]);

        $note = $coupon->logs()->create($request->input());

        return response()->json([
            'data' => $note->load('admin'),
            'message' => __('New log has been created.'),
        ], 200);
    }

    public function plans(Request $request)
    {
        $query = Plan::where('is_active', true);

        if ($request->filled('filter')) {
            $query->where(function ($q) use ($request) {
                $q->where('label', 'like', "%{$request->filter}%");
            });
        }

        $plans = $query->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'asc')
            ->paginate($request->rowsPerPage ?? 15);

        return new PlanCollection($plans);
    }
}
