<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SupportTicketController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(SupportTicket::class);
    }

    public function destroy(SupportTicket $support_ticket)
    {
        $support_ticket->delete();

        return response()->json([
            'message' => __('Support Ticket has been deleted successfully!'),
        ], 200);
    }

    public function destroySelected(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
        ]);

        SupportTicket::whereIn('id', $request->items)->delete();

        return response()->json([
            'message' => __('Support Tickets has been deleted successfully!'),
        ], 200);
    }

    public function restore($support_ticket)
    {
        $support_ticket = SupportTicket::onlyTrashed()->findOrFail($support_ticket);
        $support_ticket->restore();

        return response()->json([
            'message' => __('Support Ticket has been restored successfully!'),
        ], 200);
    }

    public function restoreSelected(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
        ]);

        SupportTicket::onlyTrashed()
            ->whereIn('id', $request->items)
            ->restore();

        return response()->json([
            'message' => __('Support Tickets has been restored successfully!'),
        ], 200);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $support_ticket = SupportTicket::query();

        if ($request->filled('filter')) {
            $support_ticket->where('subject', 'like', "%{$request->filter}%")
                ->orWhere('email', 'like', "%{$request->filter}%");
        }

        if ($request->filled('type')) {
            $support_ticket->whereType($request->type);
        }

        $support_ticket->onlyStatus($request->status);

        if ($request->boolean('deleted')) {
            $support_ticket->onlyTrashed();
        }

        if (is_user()) {
            $support_ticket->onlyOwner();
        }

        $support_ticket = $support_ticket->sortBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?: 15);

        return new ResourceCollection($support_ticket);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request, SupportTicket $support_ticket)
    {
        $rules = [
            'subject' => 'required',
            'message' => 'required',
            'user' => 'required_if:admin,true|array',
        ];

        $request->validate($rules);

        if ($request->boolean('bulk')) {
            collect($request->input('user'))->each(function ($user) use ($support_ticket, $request) {
                $request->merge([
                    'name' => $user['name'],
                    'email' => $user['email'],
                ]);

                $support_ticket = $support_ticket->create($request->input());

                // Update media
                if ($request->filled('media')) {
                    $support_ticket = $support_ticket->syncMedia($request->input('media'));
                }
            });

            return response()->json([
                'message' => __('Support ticket has been created successfully!'),
            ], 200);
        }

        if ($request->filled('user')) {
            $request->merge([
                'name' => $request->input('user.name'),
                'email' => $request->input('user.email'),
            ]);
        }

        $support_ticket = $support_ticket->create($request->input());

        // Update media
        if ($request->filled('media')) {
            $support_ticket = $support_ticket->syncMedia($request->input('media'));
        }

        return response()->json([
            'data' => $support_ticket->load(['user', 'replies.user', 'media', 'admin']),
            'message' => __('Support ticket has been created successfully!'),
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  SupportTicket  $support_ticket
     * @return Response
     */
    public function show(Request $request)
    {
        $support_ticket = SupportTicket::findOrFail($request->id);
        $support_ticket = $support_ticket->markedAsSeen();

        return response()->json($support_ticket->load(['user', 'replies.user', 'media', 'order', 'admin']), 200);
    }

    /**
     * Create reply for the specified resource.
     *
     * @return Response
     */
    public function reply(Request $request, SupportTicket $support_ticket)
    {
        $request->validate([
            'message' => 'required',
        ]);

        $reply = $support_ticket->createReply($request->input());

        // Update media
        if ($request->filled('media')) {
            $reply = $reply->syncMedia($request->input('media'));
        }

        // Update support_ticket status
        if ($request->filled('status')) {
            $support_ticket->update($request->only(['status']));
        }

        return response()->json([
            'data' => $reply->fresh(['media', 'user']),
            'message' => __('Reply has been created successfully!'),
        ], 200);
    }

    /**
     * Change archived of specified resource from storage.
     *
     * @return Response
     */
    public function changeArchived(Request $request, SupportTicket $support_ticket)
    {
        $support_ticket->update([
            'is_archived' => ! $support_ticket->is_archived,
        ]);

        $type = ! $support_ticket->is_archived ? 'archived' : 'unarchive';

        return response()->json([
            'message' => __('Support ticket marked as :type successfully!', ['type' => __($type)]),
        ], 200);
    }

    /**
     * Change user archived of specified resource from storage.
     *
     * @return Response
     */
    public function changeUserArchived(Request $request, SupportTicket $support_ticket)
    {
        $support_ticket->update([
            'user_archived' => ! $support_ticket->user_archived,
        ]);

        $type = ! $support_ticket->is_archived ? 'archived' : 'unarchive';

        return response()->json([
            'message' => __('Support ticket marked as :type successfully!', ['type' => __($type)]),
        ], 200);
    }
}
