<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Facades\Currency;
use Foundry\Foundry;
use Foundry\Models\Subscription\Feature;
use Foundry\Models\Subscription\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Gate;

class PlanController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Plan::class, 'plan', [
            'except' => ['show'],
        ]);
    }

    public function index(Request $request)
    {
        $plan = Plan::whereNull('variant_id');

        if ($request->filled('filter')) {
            $plan->where('label', 'like', "%{$request->filter}%");
        }

        if ($request->boolean('active')) {
            $plan->onlyActive();
        }

        if ($request->filled('plan_id')) {
            $plan->orWhere('id', $request->plan_id);
        }

        if ($request->boolean('deleted')) {
            $plan->onlyTrashed();
        }

        $plan = $plan->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?? 15);

        return new ResourceCollection($plan);
    }

    public function store(Request $request, Plan $plan)
    {
        $rules = [
            'label' => 'required',
            'interval' => 'required',
            'interval_count' => 'required',
            'price' => 'required',
        ];

        // Validate those rules
        $request->validate($rules);

        // create the plan
        $plan = Plan::create($request->input());

        if ($request->filled('features')) {
            $plan->syncFeatures($request->features);
        }

        return response()->json([
            'data' => $this->toArray($plan->fresh('features')),
            'message' => __('Plan has been created successfully!'),
        ], 200);
    }

    public function show($plan)
    {
        $plan = Plan::withTrashed()->findOrFail($plan);

        Gate::authorize('view', $plan);

        return response()->json($this->toArray($plan->load('features')), 200);
    }

    public function update(Request $request, Plan $plan)
    {
        $rules = [
            'label' => 'required',
            'interval' => 'required',
            'interval_count' => 'required',
            'price' => 'required',
        ];

        // Validate those rules
        $request->validate($rules);

        // update the plan
        $plan->update($request->input());

        if ($request->filled('features')) {
            $plan->syncFeatures($request->features);
        }

        return response()->json([
            'data' => $this->toArray($plan->fresh('features')),
            'message' => __('Plan has been updated successfully!'),
        ], 200);
    }

    public function destroy(Request $request, Plan $plan)
    {
        $this->authorize('delete', $plan);

        if ($plan->subscriptions()->count() > 0) {
            return response()->json([
                'message' => __('Cannot delete plan with active subscriptions.'),
            ], 422);
        }

        if ($request->boolean('force')) {
            $plan->forceDelete();
            $message = __('Plan has been permanently deleted successfully!');
        } else {
            $plan->delete();
            $message = __('Plan has been deleted successfully!');
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

        $query = Plan::whereIn('id', $request->items);

        if ($request->boolean('force')) {
            $query->forceDelete();
            $message = __('Plans has been permanently deleted successfully!');
        } else {
            $query->delete();
            $message = __('Plans has been deleted successfully!');
        }

        return response()->json([
            'message' => $message,
        ], 200);
    }

    public function restore($plan)
    {
        $plan = Plan::onlyTrashed()->findOrFail($plan);
        $plan->restore();

        return response()->json([
            'message' => __('Plan has been restored successfully!'),
        ], 200);
    }

    public function restoreSelected(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
        ]);

        Plan::onlyTrashed()
            ->whereIn('id', $request->items)
            ->restore();

        return response()->json([
            'message' => __('Plans has been restored successfully!'),
        ], 200);
    }

    public function changeActive(Request $request, Plan $plan)
    {
        $plan->update([
            'is_active' => ! $plan->is_active,
        ]);

        $type = $plan->active ? 'active' : 'deactive';

        return response()->json([
            'message' => __('Plan marked as :type successfully!', ['type' => __($type)]),
        ], 200);
    }

    public function shared(Request $request)
    {
        $plans = Plan::onlyActive();

        if ($request->filled('subscription')) {
            $subscription = Foundry::$subscriptionModel::find($request->subscription);
            if ($variant = $subscription?->plan?->variant_id) {
                $plans->where('variant_id', $variant);
            } else {
                $plans->whereNull('variant_id');
            }
        } else {
            $plans->whereNull('variant_id');
        }

        if ($request->filled('plan_id')) {
            $plans->orWhere('id', $request->plan_id);
        }

        $plans = $plans->get();

        // Convert prices to user currency automatically using Currencyable interface
        $plans = Currency::transform($plans);

        return response()->json($plans, 200);
    }

    public function features(Request $request)
    {
        return response()->json(Feature::all(), 200);
    }

    private function toArray(Plan $plan)
    {
        return array_merge($plan->toArray(), [
            'features' => $plan->features->mapWithKeys(function ($item) {
                if ($item->isBoolean()) {
                    return [$item->slug => (bool) $item->pivot->value];
                }

                return [$item->slug => $item->pivot->value];
            })->toArray(),
        ]);
    }
}
