<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notification = Notification::query();

        if ($request->has('filter') && ! empty($request->filter)) {
            $notification->where('label', 'like', "%{$request->filter}%");
            $notification->orWhere('type', 'like', "%{$request->filter}%");
        }

        if ($request->input('deleted') ? $request->boolean('deleted') : false) {
            $notification->onlyTrashed();
        }

        $notification = $notification->orderBy(optional($request)->sortBy ?? 'created_at', optional($request)->direction ?? 'desc')
            ->paginate(optional($request)->rowsPerPage ?? 15);

        return new ResourceCollection($notification);
    }

    public function store(Request $request)
    {
        $rules = [
            'label' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'content' => 'required|string',
            'subject' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
        ];

        $data = $request->validate($rules);

        $notification = Notification::create($data);

        if ($request->boolean('is_default')) {
            $notification->markAsDefault();
        }

        return response()->json([
            'data' => $notification,
            'message' => __('Notification has been created successfully!'),
        ], 200);
    }

    public function show(Notification $notification)
    {
        return response()->json($notification, 200);
    }

    public function update(Request $request, Notification $notification)
    {
        $rules = [
            'label' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:100',
            'content' => 'sometimes|required|string',
            'subject' => 'sometimes|nullable|string|max:255',
            'is_default' => 'sometimes|nullable|boolean',
        ];

        $data = $request->validate($rules);

        $notification->update($data);

        if ($request->boolean('is_default')) {
            $notification = $notification->markAsDefault();
        }

        return response()->json([
            'data' => $notification,
            'message' => __('Notification has been updated successfully!'),
        ], 200);
    }

    public function destroy(Notification $notification)
    {
        if ($notification->is_default) {
            abort(422, 'Default notification can\'t be deleted');
        }

        $notification->forceDelete();

        return response()->json([
            'message' => __('Notification has been deleted successfully!'),
        ], 200);
    }

    public function markAsDefault(Request $request, Notification $notification)
    {
        $notification->markAsDefault();

        return response()->json([
            'data' => $notification,
            'message' => __('Template marked as default successfully!'),
        ], 200);
    }

    public function duplicate(Request $request, Notification $notification)
    {
        $notification = $notification->duplicate();

        return response()->json([
            'data' => $notification,
            'message' => __('Template has been duplicated successfully!'),
        ], 200);
    }
}
