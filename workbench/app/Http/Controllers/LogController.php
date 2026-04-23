<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(Log $log)
    {
        return response()->json($log->load([
            'user',
            'media',
        ]), 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(Request $request, Log $log)
    {
        // Set rules
        $rules = [
            'message' => 'required',
        ];

        // Validate those rules
        $this->validate($request, $rules);

        $log->update($request->input());

        // Update media
        if ($request->has('media')) {
            $log->setMedia($request->input('media'));
        }

        return response()->json([
            'data' => $log,
            'message' => __('Log has been updated successfully!'),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(Log $log)
    {
        $log->delete();

        return response()->json([
            'message' => __('Log has been deleted successfully!'),
        ], 200);
    }

    /**
     * Store a reply to specified resource in storage.
     *
     * @return Response
     */
    public function reply(Request $request, Log $log)
    {
        $rules = [
            'message' => 'required',
        ];

        $this->validate($request, $rules);

        $log = $log->reply()->create($request->input());

        // Update media
        if ($request->has('media')) {
            $log->setMedia($request->input('media'));
        }

        return response()->json([
            'data' => $log->load('admin'),
            'message' => __('Log reply has been created successfully!'),
        ], 200);
    }
}
