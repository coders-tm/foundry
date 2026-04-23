<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Http\Resources\Coupon\PlanCollection;
use Foundry\Http\Resources\CouponResource;
use Foundry\Models\Coupon;
use Foundry\Models\Shop\Product;
use Foundry\Models\Subscription\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Controllers\Subscription\CouponController as BaseCouponController;

class CouponController extends BaseCouponController
{
    public function index(Request $request)
    {
        $coupon = Coupon::query()->with(['plans', 'products']);

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
            'type' => 'required',
            'name' => 'required',
            'promotion_code' => 'required|unique:coupons,promotion_code',
            'duration' => 'required',
            'duration_in_months' => 'required_if:duration,repeating',
            'discount_type' => 'required|in:percentage,fixed,override',
            'value' => 'required|numeric|min:0',
            'products' => 'sometimes|array',
            'plans' => 'sometimes|array',
        ];

        // Validate those rules
        $this->validate($request, $rules);

        // create the coupon
        $coupon = Coupon::create($request->input());

        // Sync relationships
        if ($request->has('plans')) {
            $coupon->syncPlans($request->plans);
        }

        if ($request->has('products')) {
            $coupon->syncProducts($request->products);
        }

        return response()->json([
            'data' => new CouponResource($coupon->fresh(['plans', 'products', 'logs'])),
            'message' => __('Coupon has been created successfully!'),
        ], 200);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $rules = [
            'name' => 'required',
            'promotion_code' => 'required|unique:coupons,promotion_code,'.$coupon->id,
            'value' => 'required|numeric|min:0',
            'products' => 'sometimes|array',
            'plans' => 'sometimes|array',
        ];

        // Validate those rules
        $this->validate($request, $rules);

        // update the coupon
        $coupon->update($request->only([
            'name',
            'promotion_code',
            'active',
            'auto_apply',
        ]));

        // Sync relationships
        if ($request->has('plans')) {
            $coupon->syncPlans($request->plans);
        }

        if ($request->has('products')) {
            $coupon->syncProducts($request->products);
        }

        return response()->json([
            'data' => new CouponResource($coupon->fresh(['plans', 'products', 'logs'])),
            'message' => __('Coupon has been updated successfully!'),
        ], 200);
    }

    /**
     * Get plans for select options (includes product information)
     */
    public function plans(Request $request)
    {
        $query = Plan::query()->where('is_active', true);

        if ($request->filled('filter')) {
            $query->where('label', 'like', "%{$request->filter}%");
        }

        $plans = $query->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'asc')
            ->paginate($request->rowsPerPage ?? 15);

        return new PlanCollection($plans);
    }
}
