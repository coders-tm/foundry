<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $file = File::query();

        if ($request->filled('filter')) {
            $file->where('original_file_name', 'like', "%{$request->filter}%");
        }

        if ($request->filled('type')) {
            $file->where('mime_type', 'like', "%{$request->type}%");
        }

        if ($request->input('deleted') ? $request->boolean('deleted') : false) {
            $file->onlyTrashed();
        }

        $file = $file->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?? 15);

        return new ResourceCollection($file);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'media' => 'required',
            'disk' => 'sometimes|string|in:local,public,s3,cloud', // Validate disk against allowed list
        ]);

        $disk = $request->input('disk', config('filesystems.default'));

        if ($request->filled('assets')) {
            $assets = [];

            foreach ($request->file('media') as $asset) {
                $file = new File([
                    'disk' => $disk,
                ]);
                $file->setHttpFile($asset);
                $file->save();
                $assets[] = $file->url;
            }

            return response()->json(['data' => $assets], 200);
        }

        $file = new File([
            'disk' => $disk,
        ]);
        $file->setHttpFile($request->file('media'));
        $file->save();

        return response()->json(new JsonResource($file->fresh()), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  File  $file
     * @return Response
     */
    public function show($file)
    {
        $file = File::withTrashed()->findOrFail($file);

        // Gate::authorize('view', $file);

        return response()->json(new JsonResource($file), 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(Request $request, File $file)
    {
        $rules = [
            'media' => 'required',
        ];

        $this->validate($request, $rules);

        $file->setHttpFile($request->file('media'));
        $file->modify();

        return response()->json(new JsonResource($file->fresh()), 200);
    }

    /**
     * Download the specified resource from storage.
     *
     * @return Response
     */
    public function download(Request $request)
    {
        try {
            $file = File::findByHash($request->hash ?? '');

            if ($request->has('download')) {
                return Storage::disk($file->disk)->download($file->path, $file->original_file_name);
            } elseif ($file->disk == 'cloud') {
                return response()->redirectTo(Storage::disk($file->disk)->url($file->path));
            }

            return response()->file(Storage::disk($file->disk)->path($file->path));
        } catch (\Throwable $th) {
            return abort(404, __('File not found!'));
        }
    }

    public function uploadFromSource(Request $request)
    {
        $rules = [
            'source' => 'required|url',
        ];

        $this->validate($request, $rules);

        $url = $request->input('source');
        $_path = parse_url($url)['path'];
        $paths = explode('/', $_path);
        $name = $paths[count($paths) - 1];
        try {
            $path = 'files/'.md5($url).'.png';

            // SSRF Protection
            $urlParts = parse_url($url);
            if (! isset($urlParts['scheme']) || ! in_array(strtolower($urlParts['scheme']), ['http', 'https'])) {
                throw new \Exception('Invalid URL scheme.');
            }

            $host = $urlParts['host'];
            // Resolve hostname to IP to check for private ranges
            $ips = gethostbynamel($host);
            if ($ips) {
                foreach ($ips as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                        throw new \Exception('Access to private network address is improper.');
                    }
                }
            }

            $media = Http::timeout(10)->get($url); // Add timeout

            Storage::disk('local')->put($path, $media);

            $filePath = Storage::disk('local')->path($path);

            $file = new File;
            $file->setHttpFile(new UploadedFile($filePath, $name));
            $file->save($request->input());

            // delete file
            unlink($filePath);

            return response()->json(new JsonResource($file->fresh()), 200);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function destroy(Request $request, File $file)
    {
        if ($request->boolean('force')) {
            $file->forceDelete();
            $message = __('File has been permanently deleted successfully!');
        } else {
            $file->delete();
            $message = __('File has been deleted successfully!');
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

        $query = File::whereIn('id', $request->items);

        if ($request->boolean('force')) {
            $query->forceDelete();
            $message = __('Files has been permanently deleted successfully!');
        } else {
            $query->delete();
            $message = __('Files has been deleted successfully!');
        }

        return response()->json([
            'message' => $message,
        ], 200);
    }

    public function restore($file)
    {
        $file = File::onlyTrashed()->findOrFail($file);
        $file->restore();

        return response()->json([
            'message' => __('File has been restored successfully!'),
        ], 200);
    }

    public function restoreSelected(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
        ]);

        File::onlyTrashed()
            ->whereIn('id', $request->items)
            ->restore();

        return response()->json([
            'message' => __('Files has been restored successfully!'),
        ], 200);
    }
}
