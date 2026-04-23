<?php

namespace Foundry\Services;

use Foundry\Models\Log;

class LogMessageFormatter
{
    /**
     * Format a human-readable message for a given log entry.
     *
     * @param  bool  $firstPerson  When true, login messages use "You" (current-user context).
     *                             When false, the logable's name is used instead.
     */
    public function format(Log $log, bool $firstPerson = false): string
    {
        $resource = $log->getResource();
        $model = $resource['type'] ?? __('Unknown');
        $resourceName = $resource['name'] ?? $resource['type'] ?? null;
        $adminName = $log->admin?->name ?? __('System');

        if ($log->type === 'login') {
            $options = $log->options ?? [];
            $ip = $options['ip'] ?? 'unknown';
            $device = $options['device'] ?? 'unknown';

            if ($firstPerson) {
                return __('You have logged in from :ip on :device', [
                    'ip' => $ip,
                    'device' => $device,
                ]);
            }

            $name = $log->logable?->name ?? __('Unknown');

            return __(':mode :name logged in from :ip on :device', [
                'mode' => $model,
                'name' => $name,
                'ip' => $ip,
                'device' => $device,
            ]);
        }

        if ($log->type === 'logout') {
            $options = $log->options ?? [];
            $ip = $options['ip'] ?? 'unknown';
            $device = $options['device'] ?? 'unknown';

            if ($firstPerson) {
                return __('You have logged out from :ip on :device', [
                    'ip' => $ip,
                    'device' => $device,
                ]);
            }

            $name = $log->logable?->name ?? __('Unknown');

            return __(':mode :name logged out from :ip on :device', [
                'mode' => $model,
                'name' => $name,
                'ip' => $ip,
                'device' => $device,
            ]);
        }

        return match ($log->type) {
            'created', 'updated' => __(':model ":resource" is :action by :admin', [
                'model' => $model,
                'resource' => $resourceName ?? __('Record'),
                'action' => $log->type,
                'admin' => $adminName,
            ]),
            'deleted', 'restored', 'force-deleted' => __(':model ":resource" has been :action by :admin', [
                'model' => $model,
                'resource' => $resourceName ?? __('Record'),
                'action' => $log->type,
                'admin' => $adminName,
            ]),
            default => $log->message ?? __('Unknown action'),
        };
    }
}
