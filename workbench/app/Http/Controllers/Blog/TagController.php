<?php

namespace Workbench\App\Http\Controllers\Blog;

use Foundry\Models\Blog\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Controllers\Controller;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $tag = Tag::query();

        $tag->when($request->boolean('options'), function ($query) {
            $query->withOptions();
        })->when($request->filled('filter'), function ($query) use ($request) {
            $query->where('label', 'like', "%{$request->filter}%");
        });

        $tags = $tag->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?? 15);

        // Regular tag results
        return new ResourceCollection($tags);
    }

    public function store(Request $request, Tag $tag)
    {
        // Set rules
        $rules = [
            'label' => 'required',
        ];

        // Validate those rules
        $request->validate($rules);

        $tag = $tag->create($request->input());

        return response()->json([
            'data' => $tag,
            'message' => __('Tag has been created successfully!'),
        ], 200);
    }
}
