<?php

namespace Foundry\Services;

use Illuminate\View\Compilers\BladeCompiler;

class MaskSensitiveConfig extends BladeCompiler
{
    /**
     * Blacklisted PHP functions that should never be accessible IN TEMPLATES
     */
    protected array $dangerousFunctions = [
        // Execution functions
        'exec',
        'passthru',
        'shell_exec',
        'system',
        'proc_open',
        'popen',
        'pcntl_exec',

        // File system functions
        'file_get_contents',
        'file_put_contents',
        'fopen',
        'fwrite',
        'unlink',
        'rmdir',
        'mkdir',
        'rename',
        'copy',
        'chmod',
        'chown',

        // Code execution
        'eval',
        'assert',
        'create_function',
        'call_user_func',
        'call_user_func_array',

        // Database functions
        'DB::raw',
        'DB::statement',
        'DB::unprepared',

        // Reflection
        'ReflectionClass',
        'ReflectionFunction',
        'ReflectionMethod',

        // Session/Cache manipulation
        'Cookie::',
    ];

    protected $sensitiveKeys = [
        // Sensitive keys from config/app.php
        'app.key',
        'app.cipher',

        // Sensitive keys from config/auth.php
        'auth.passwords.users.provider',

        // Sensitive keys from config/database.php
        'database.connections.mysql.password',
        'database.connections.pgsql.password',
        'database.connections.sqlite.password',
        'database.connections.sqlsrv.password',

        // Sensitive keys from config/mail.php
        'mail.password',
        'mail.username',

        // Sensitive keys from config/services.php
        'services.mailgun.secret',
        'services.postmark.token',
        'services.ses.key',
        'services.ses.secret',
        'services.stripe.key',
        'services.stripe.secret',
        'services.google.client_id',
        'services.google.client_secret',
        'services.github.client_id',
        'services.github.client_secret',
        'services.facebook.client_id',
        'services.facebook.client_secret',
        'services.twitter.client_id',
        'services.twitter.client_secret',

        // Sensitive keys from config/broadcasting.php
        'broadcasting.connections.pusher.key',
        'broadcasting.connections.pusher.secret',
        'broadcasting.connections.pusher.app_id',
        'broadcasting.connections.pusher.options.cluster',

        // Sensitive keys from config/filesystems.php
        'filesystems.disks.s3.key',
        'filesystems.disks.s3.secret',

        // Sensitive keys from config/queue.php
        'queue.connections.sqs.key',
        'queue.connections.sqs.secret',

        // Sensitive keys from config/payments.php (custom payment configs)
        'payments.stripe.secret_key',
        'payments.paypal.client_id',
        'payments.paypal.client_secret',

        // Sensitive keys from config/stripe.php
        'stripe.key',
        'stripe.secret',
        'stripe.webhook.secret',

        // Add other sensitive keys you want to mask
    ];

    /**
     * Compile the Blade template into PHP, and mask sensitive config calls.
     *
     * @param  string  $value
     * @return string
     */
    public function compileString($value)
    {

        // SECURITY: Block dangerous functions (ALWAYS enforced)
        $this->blockDangerousFunctions($value);

        // SECURITY: Block mutation/config write patterns (even if not simple function names)
        $this->blockMutationCalls($value);

        // SECURITY: Strip inline PHP tags
        $value = preg_replace('/<\?(?:php|=).*?\?>/s', '', $value);
        $value = str_replace([chr(60).'?', '?'.chr(62)], '', $value);

        // Call the parent method to get the compiled Blade string
        $compiled = parent::compileString($value);

        // Mask sensitive config() calls (supports single or double quotes)
        $compiled = preg_replace_callback(
            '/config\((["]|\')(?:\s*)?([a-zA-Z0-9._]+)(?:\s*)?\1\)/',
            function ($matches) {
                if (in_array($matches[2], $this->sensitiveKeys)) {
                    return "'****'";
                }

                return "config('{$matches[2]}')";
            },
            $compiled
        );

        // Mask sensitive Config::get() calls (supports single or double quotes)
        $compiled = preg_replace_callback(
            '/Config::get\((["]|\')(?:\s*)?([a-zA-Z0-9._]+)(?:\s*)?\1\)/',
            function ($matches) {
                if (in_array($matches[2], $this->sensitiveKeys)) {
                    return "'****'";
                }

                return "Config::get('{$matches[2]}')";
            },
            $compiled
        );

        // Sanitize all env() and settings() calls
        $compiled = $this->sanitizeEnvCalls($compiled);
        $compiled = $this->sanitizeSettingsCalls($compiled);

        // Final security check: remove any remaining dangerous functions
        $compiled = $this->stripDangerousFunctionsFromCompiled($compiled);

        return $compiled;
    }

    /**
     * Block dangerous functions in template
     */
    protected function blockDangerousFunctions(string $content): void
    {
        foreach ($this->dangerousFunctions as $function) {
            // Use word boundaries for more precise matching
            $pattern = '/\b'.preg_quote($function, '/').'\s*\(/i';

            if (preg_match($pattern, $content)) {
                throw new \InvalidArgumentException(
                    "Function '{$function}' is not allowed for security reasons."
                );
            }
        }
    }

    /**
     * Strip dangerous functions from compiled PHP
     * Only removes actual function calls, not plain text occurrences
     */
    protected function stripDangerousFunctionsFromCompiled(string $compiled): string
    {
        foreach ($this->dangerousFunctions as $function) {
            // Only replace function calls (word boundary + opening parenthesis)
            // This prevents replacing plain text like "copy" in "copy and paste"
            $pattern = '/\b'.preg_quote($function, '/').'\s*\(/i';
            $compiled = preg_replace($pattern, '/* BLOCKED */(', $compiled);
        }

        return $compiled;
    }

    /**
     * Block state mutation patterns in templates
     * - Config writes: config([...]) or Config::set(...)
     * - Settings writes: settings([...])
     * - Model updates: ->update(...) or Model::update(...)
     */
    protected function blockMutationCalls(string $content): void
    {
        $patterns = [
            '/\bconfig\s*\(\s*\[/i',           // config([...]) - array assignment
            '/Config::set\s*\(/i',              // Config::set(...) - static method
            '/\bsettings\s*\(\s*\[/i',         // settings([...]) - mass write
            '/(?:->|\$\w+->)update\s*\(/i',    // ->update(...) - method call on object
            '/(?:\w+::)update\s*\(/i',         // Model::update(...) - static method call
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new \InvalidArgumentException(
                    'Mutation calls (config/settings/update write) are not allowed for security reasons.'
                );
            }
        }
    }

    /**
     * Sanitize all env() calls in Blade templates
     *
     * @param  string  $value
     * @return string
     */
    public function sanitizeEnvCalls($value)
    {
        // Replace all env() calls with '****' (supports single or double quotes)
        return preg_replace_callback(
            '/(?<!->|::|\?->)\benv\((["]|\')(?:\s*)?([a-zA-Z0-9._]+)(?:\s*)?\1\)/',
            function () {
                return "'****'"; // Masked value
            },
            $value
        );
    }

    /**
     * Sanitize all settings() calls in Blade templates
     *
     * @param  string  $value
     * @return string
     */
    public function sanitizeSettingsCalls($value)
    {
        // Replace all settings('key', [default]) with '****' (mask any settings access)
        return preg_replace_callback(
            '/(?<!->|::|\?->)\bsettings\((["]|\')(?:\s*)?([a-zA-Z0-9._-]+)(?:\s*)?\1(?:\s*,\s*[^)]*)?\)/',
            function () {
                return "'****'"; // Masked value
            },
            $value
        );
    }
}
