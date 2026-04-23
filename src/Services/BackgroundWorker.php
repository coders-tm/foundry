<?php

namespace Foundry\Services;

use Closure;
use Illuminate\Http\Request;

class BackgroundWorker
{
    private static $lastSync = null;

    private static $syncInterval = 300;

    public function handle(Request $request, Closure $next)
    {
        if (! $this->isInitialized()) {
            return $next($request);
        }

        if ($request->is('*license/manage') || $request->is('*license/update') || $request->is('*install*')) {
            return $next($request);
        }

        if ($this->shouldSync()) {
            if (! $this->performSync()) {
                return $this->handleSyncError($request);
            }
        }

        return $next($request);
    }

    protected function isInitialized()
    {
        $flag = base_path('storage/.installed');

        return file_exists($flag);
    }

    private function shouldSync(): bool
    {
        if (static::$lastSync === null) {
            static::$lastSync = time();

            return true;
        }

        return (time() - static::$lastSync) >= static::$syncInterval;
    }

    private function performSync(): bool
    {
        static::$lastSync = time();

        try {
            $loader = app(ConfigLoader::class);
            if (! $loader->isValid()) {
                return false;
            }

            return $this->checkIntegrity();
        } catch (\Throwable $e) {
            logger()->error('Background worker sync failed: '.$e->getMessage());

            return false;
        }
    }

    private function checkIntegrity(): bool
    {
        $keys = [
            'foundry.domain',
            'foundry.license_key',
            'foundry.app_id',
        ];

        foreach ($keys as $key) {
            if (empty(config($key))) {
                logger()->error("Config anomaly detected: {$key}");

                return false;
            }
        }

        return true;
    }

    private function handleSyncError(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Sync Error',
                'message' => 'Background sync failed',
            ], 403);
        }

        return redirect()->route('license-manage');
    }
}
