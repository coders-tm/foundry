<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Models\Setting;
use Coderstm\PageBuilder\Services\Theme;
use Foundry\Services\Helpers;
use Foundry\Services\Theme\FileMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ThemeController extends Controller
{
    protected $basePath = null;

    public $homeRoute = 'home';

    // List all themes
    public function index()
    {
        $themesPath = config('theme.base_path');
        $themeFolders = File::directories($themesPath);

        $themes = array_map(function ($folder) {
            $configFile = $folder . '/config.json';
            if (File::exists($configFile)) {
                $config = json_decode(File::get($configFile), true);
                $config['key'] = basename($folder); // Add theme path to the response
                $config['active'] = Theme::active() == $config['key']; // Add theme active to the response
                $config['modified_at'] = date('Y-m-d H:i:s', filemtime($folder));
                $config['preview'] = route($this->homeRoute, ['theme' => $config['key']]);

                return $config;
            }

            return null;
        }, $themeFolders);

        return response()->json(array_filter($themes), 200);
    }

    // Activate a theme
    public function activate($theme)
    {
        $configPath = Theme::basePath('config.json', $theme);

        if (File::exists($configPath)) {
            Theme::set($theme);
            Setting::updateValue('theme', ['active' => $theme]);

            return response()->json(['message' => __('Theme activated successfully')], 200);
        }

        return response()->json(['message' => __('Theme not found!')], 404);
    }

    // Delete a theme
    public function destroy($theme)
    {
        $defaultTheme = 'foundation';
        $activeTheme = Theme::active();

        // Prevent deletion of default or active theme
        if ($theme === $defaultTheme || $theme === $activeTheme) {
            return response()->json(['message' => __('Cannot delete the default or active theme!')], 403);
        }

        $themePath = Theme::basePath('', $theme);
        if (File::exists($themePath)) {
            File::deleteDirectory($themePath);

            return response()->json(['message' => __('Theme deleted successfully!')], 200);
        }

        return response()->json(['message' => __('Theme not found!')], 404);
    }

    // Clone a theme
    public function clone($theme)
    {
        $config = Theme::config($theme);
        $newThemeName = $config['name'] . ' (Copy)';
        $newThemeKey = Str::slug($theme . '-' . now()->timestamp);

        $themePath = Theme::basePath('', $theme);
        $newThemePath = Theme::basePath('', $newThemeKey);

        $themeMixPath = Theme::assetsPath($theme);
        $newThemeMixPath = Theme::assetsPath($newThemeKey);

        if (File::exists($themePath) && ! File::exists($newThemePath)) {
            // Clone theme directory
            File::copyDirectory($themePath, $newThemePath);
            File::copyDirectory($themeMixPath, $newThemeMixPath);

            // Update the config.json of the cloned theme
            $config = Theme::config($newThemeKey);
            $config['name'] = $newThemeName;
            $config['parent'] = $theme;

            File::put(Theme::basePath('config.json', $newThemeKey), json_encode($config, JSON_PRETTY_PRINT));

            return response()->json(['message' => __('Theme cloned successfully, theme build queued.')], 200);
        }

        return response()->json(['message' => __('Theme not found or new theme already exists')], 404);
    }

    // Get the list of files and directories in a theme with `basepath` for the editor
    public function getFiles($theme)
    {
        $themePath = Theme::basePath($this->basePath, $theme);
        $assetsPath = Theme::assetsPath($theme);

        if (! File::exists($themePath)) {
            return response()->json(['message' => __('Theme not found')], 404);
        }

        $fileTree = $this->getDirectoryStructure($themePath);
        $themeInfo = $this->info($theme);
        $assetsTree = $this->getDirectoryStructure($assetsPath, 'assets');

        $fileTree = collect($fileTree)->map(function ($item) use ($assetsTree) {
            if (isset($item['basepath']) && Str::startsWith($item['basepath'], 'assets')) {
                $item['children'] = $assetsTree;
            }

            return $item;
        });

        return response()->json([
            'files' => $fileTree->values(),
            'info' => $themeInfo,
        ], 200);
    }

    // Recursive function to build file and folder structure
    private function getDirectoryStructure($directory, $prefix = null, $basepath = null)
    {
        $items = [];
        $basepath = $basepath ?? $directory;

        // Get all directories and files in the current directory
        $directories = File::directories($directory);
        $files = File::files($directory);

        // Add directories to the structure
        foreach ($directories as $dir) {
            $dirName = basename($dir);
            $relativePath = str_replace($basepath . '/', '', $dir); // Get relative path
            $relativePath = $prefix ? $prefix . '/' . $relativePath : $relativePath;

            if (! in_array($dirName, ['public'])) {
                $singular = Helpers::singularizeDirectoryName($dirName);
                $assets = $dirName === 'assets';
                $items[] = [
                    'name' => $dirName,
                    'addLabel' => "Add a new $singular",
                    'basepath' => $relativePath,
                    'header' => 'directory',
                    'modified_at' => date('Y-m-d H:i:s', filemtime($dir)),
                    'children' => $assets ? [] : $this->getDirectoryStructure($dir, $prefix, $basepath),
                ];
            }
        }

        // Add files to the structure
        foreach ($files as $file) {
            $items[] = (new FileMeta($file, $basepath, $prefix))->toArray();
        }

        return $items;
    }

    public function getFileContent($theme, Request $request)
    {
        $filePath = $request->input('key');  // The relative path of the selected file

        if (Str::startsWith($filePath, 'assets')) {
            $filePath = str_replace('assets/', '', $filePath);
            $fullPath = Theme::assetsPath($theme, $filePath);
        } else {
            $fullPath = Theme::basePath("{$this->basePath}/$filePath", $theme);
        }

        $fullPath = realpath($fullPath);

        if (! $fullPath || ! File::exists($fullPath)) {
            return response()->json(['message' => __('File not found or invalid path')], 404);
        }

        // Read the file content
        $content = File::get($fullPath);

        return response()->json([
            'file' => $filePath,
            'content' => $content,
        ], 200);
    }

    // Save edited file content
    public function saveFile(Request $request, $theme)
    {
        $filePath = $request->input('key');
        $content = $request->input('content');

        if (Str::startsWith($filePath, 'assets')) {
            $filePath = str_replace('assets/', '', $filePath);
            $fullPath = Theme::assetsPath($theme, $filePath);
        } else {
            $fullPath = Theme::basePath("{$this->basePath}/$filePath", $theme);
        }

        $themePath = realpath($fullPath);

        // Validate Blade syntax with security checks (if it's a .blade.php file)
        if (File::extension($filePath) === 'php') {
            try {
                Blade::compileString($content);
            } catch (\Throwable $e) {
                return response()->json(['message' => __('Security or syntax error') . ': ' . $e->getMessage()], 400);
            }
        }

        // Save file content
        File::put($themePath, $content);

        return response()->json(['message' => __('File saved successfully')], 200);
    }

    public function createFile(Request $request, $theme)
    {
        $request->validate([
            'name' => 'required|string',
            'ext' => 'required|string',
            'basepath' => 'required|string',
            'template' => 'nullable|string', // Template is optional
        ]);

        $fileName = $request->input('name') . $request->ext;
        $basepath = rtrim($request->input('basepath'), '/');
        $themePath = Theme::basePath("{$this->basePath}/$basepath", $theme);

        if (! File::exists($themePath)) {
            return response()->json(['message' => __('Theme not found')], 404);
        }

        $filePath = Theme::basePath("{$this->basePath}/$basepath/$fileName", $theme);

        // Check if the file already exists
        if (File::exists($filePath)) {
            return response()->json(['message' => __('File already exists')], 400);
        }

        // If a template is provided, copy the content of the template file
        if ($request->filled('template')) {
            $templatePath = $themePath . '/' . $request->template;

            // Check if the template file exists
            if (! File::exists($templatePath)) {
                return response()->json(['message' => __('Template file not found')], 404);
            }

            // Create the new file with the content of the template
            $templateContent = File::get($templatePath);
            File::put($filePath, $templateContent);
        } else {
            // Create an empty file if no template is provided
            File::put($filePath, '');
        }

        return response()->json([
            'message' => __('File created successfully'),
            'file' => [
                'name' => $fileName,
                'basepath' => str_replace(Theme::basePath($this->basePath, $theme) . '/', '', $filePath),
                'icon' => 'fas fa-code',
                'header' => 'file',
            ],
        ], 201);
    }

    public function destroyThemeFile(Request $request, $theme)
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        $filePath = $request->input('key');  // The relative path of the selected file
        $fullPath = realpath(Theme::basePath($this->basePath . '/' . $filePath, $theme));

        // Check if the file exists
        if (! $fullPath || ! File::exists($fullPath)) {
            return response()->json(['message' => __('File not found or invalid path')], 404);
        }

        // Prevent deletion of specific directories or files (like 'public' or 'preview.png')
        if (str_contains($filePath, 'public') || str_contains($filePath, 'preview.png') || str_contains($filePath, 'config.json')) {
            return response()->json(['message' => __('This file or directory cannot be deleted')], 403);
        }

        // Attempt to delete the file
        try {
            File::delete($fullPath);

            return response()->json(['message' => __('File deleted successfully')], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => __('Error deleting file'), 'error' => $e->getMessage()], 500);
        }
    }

    public function assetsUpload(Request $request, $theme)
    {
        $request->validate([
            'media' => [
                'required',
                'mimetypes:image/jpeg,image/png,image/gif',
                'max:300', // 300 KB size limit
            ],
        ], [
            'media.required' => 'Please select an image to upload.',
            'media.mimetypes' => 'The file must be an image (JPEG, PNG, GIF).',
            'media.max' => 'The file must not be larger than 300 KB.',
        ]);

        // Define the directory path for the theme assets
        $fileName = $request->file('media')->getClientOriginalName();
        $filePath = Theme::basePath("{$this->basePath}assets/img/$fileName", $theme);

        // Check if the file already exists
        if (File::exists($filePath)) {
            return response()->json(['message' => __('File already exists')], 422);
        }

        // Move the uploaded file to the designated path
        $request->file('media')->move(dirname($filePath), $fileName);

        return response()->json([
            'name' => $fileName,
            'basepath' => str_replace(Theme::basePath($this->basePath, $theme) . '/', '', $filePath),
            'icon' => 'fas fa-image',
            'header' => 'file',
        ], 201);
    }

    public function assets(Request $request, $theme)
    {
        // Sanitize the path to prevent directory traversal
        $path = str_replace(['..', './', '\\'], '', $request->input('path')); // Remove directory traversal sequences

        if (Str::endsWith($path, '.php')) {
            abort(404);
        }

        // Generate the full file path for the theme
        $filePath = Theme::basePath($path, $theme);

        // Use realpath to get the absolute path and ensure it's within the allowed directories
        $realFilePath = realpath($filePath);

        // Check if the real path is valid and within the intended theme directories
        if ($realFilePath && File::exists($realFilePath)) {
            // Return the file with headers
            return response()->file($realFilePath);
        }

        // Abort with a 404 if the file is not found or invalid
        abort(404);
    }

    // Show selected theme details
    protected function info($theme)
    {
        $folder = Theme::basePath('', $theme);
        $configFile = Theme::basePath('config.json', $theme);

        if (File::exists($configFile)) {
            $config = json_decode(File::get($configFile), true);
            $config['key'] = $theme; // Add theme path to the response
            $config['active'] = Theme::active() == $theme; // Add theme active to the response
            $config['modified_at'] = date('Y-m-d H:i:s', filemtime($folder));
            $config['preview'] = route($this->homeRoute, ['theme' => $theme]);

            return $config;
        }

        return null;
    }
}
