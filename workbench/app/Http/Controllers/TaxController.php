<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Models\Tax;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Collection
     */
    public function destroy(Tax $tax)
    {
        $tax->delete();

        return response()->json([
            'message' => __('Tax has been deleted successfully!'),
        ], 200);
    }

    public function destroySelected(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
        ]);

        Tax::whereIn('id', $request->items)->delete();

        return response()->json([
            'message' => __('Taxes has been deleted successfully!'),
        ], 200);
    }

    public function index(Request $request)
    {
        $tax = Tax::query();

        return $tax->orderBy($request->sortBy ?? 'code', $request->direction ?? 'desc')
            ->orderBy('priority')
            ->get();
    }

    public function store(Request $request)
    {
        $rules = [
            'country' => 'required',
            'code' => 'required',
            'state' => 'required',
            'label' => 'required',
            'rate' => 'required',
        ];

        // Validate those rules
        $request->validate($rules);

        // create the tax
        $tax = Tax::create($request->input());

        return response()->json([
            'data' => $tax,
            'message' => __('Tax has been created successfully!'),
        ], 200);
    }

    public function update(Request $request, Tax $tax)
    {
        $rules = [
            'country' => 'required',
            'code' => 'required',
            'state' => 'required',
            'label' => 'required',
            'rate' => 'required',
        ];

        // Validate those rules
        $request->validate($rules);

        // update the tax
        $tax->update($request->input());

        return response()->json([
            'data' => $tax->fresh(),
            'message' => __('Tax has been updated successfully!'),
        ], 200);
    }
}
