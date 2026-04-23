<?php

namespace Foundry\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PreserveJsonWhitespace
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only process JSON requests with data field
        if ($request->isJson() && $request->has('data')) {
            // Get the raw JSON content
            $rawContent = $request->getContent();

            if (! empty($rawContent)) {
                try {
                    // Decode the raw JSON to preserve whitespace
                    $rawData = json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR);

                    if (isset($rawData['data'])) {
                        // Replace the trimmed data with the original data from raw JSON
                        $request->merge([
                            'data' => $rawData['data'],
                        ]);
                    }
                } catch (\JsonException $e) {
                    // If JSON parsing fails, continue with the original request
                    // This allows Laravel's validation to handle the error
                }
            }
        }

        return $next($request);
    }
}
