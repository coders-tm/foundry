<?php

namespace Foundry\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class NotificationTemplateRenderer
{
    /**
     * Render a template with safe Blade compilation using MaskSensitiveConfig
     */
    public function render(string $template, array $data = []): string
    {
        try {
            // Get default Blade compiler (now MaskSensitiveConfig with security)
            $compiler = new MaskSensitiveConfig(app(Filesystem::class), storage_path('framework/views'));

            // Fix common HTML escaped characters in placeholders before rendering
            // This handles cases where people edit templates in a UI that escapes these
            $template = str_replace(['-&gt;', '&amp;'], ['->', '&'], $template);

            // Validate directives and dangerous functions (will throw if unsafe)
            $compiler->compileString($template);

            // Normalize whitespace inside moustaches to support variants like "{{ APP_NAME }}"
            $template = preg_replace('/\{\{\s+/', '{{', $template);
            $template = preg_replace('/\s+\}\}/', '}}', $template);

            // Replace UPPERCASE shortcodes first (before Blade compilation)
            // Pass data to helper which will process it internally
            $template = replace_short_code($template, $data);

            // Create a unique temporary filename
            $tempFile = 'safe_'.Str::random(40).'.blade.php';
            $tempPath = storage_path('framework/views/safe-templates/'.$tempFile);

            // Ensure directory exists
            if (! is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            // Write template to temp file
            file_put_contents($tempPath, $template);

            // Convert arrays to object form for Blade access ($obj->key)
            $processor = app(ShortcodeProcessor::class);
            $objectData = $processor->toObject($data);

            // Render using Blade
            $rendered = view()->file($tempPath, $objectData)->render();

            // Clean up temp file
            @unlink($tempPath);

            return $rendered;
        } catch (\InvalidArgumentException $e) {
            // Security validation failed - don't render, just throw
            throw new \InvalidArgumentException(
                'Template contains disallowed directives or functions: '.$e->getMessage()
            );
        } catch (\Throwable $e) {
            // Log the error
            logger()->error('Template rendering failed', [
                'error' => $e->getMessage(),
                'template' => substr($template, 0, 200),
            ]);

            // Fallback to plain shortcode replacement on original template
            return replace_short_code($template, $data);
        }
    }

    /**
     * Validate template syntax without rendering
     */
    public function validate(string $template): array
    {
        try {
            $compiler = new MaskSensitiveConfig(app(Filesystem::class), storage_path('framework/views'));
            $compiler->compileString($template);

            return ['valid' => true];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
