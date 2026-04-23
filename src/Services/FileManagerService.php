<?php

namespace Foundry\Services;

use Foundry\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class FileManagerService
{
    protected $manager;

    public function __construct()
    {
        // Defaulting to Gd for maximum compatibility as requested/standard
        // Can be configured via config/foundry.php later if needed
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Scale the image into predefined sizes.
     */
    public function scale(File $file)
    {
        if (! $file->is_image) {
            return;
        }

        $sizes = config('foundry.image_sizes', [
            'thumbnail' => [150, 150, true],
            'medium' => [300, 300, false],
            'large' => [1024, 1024, false],
        ]);

        $conversions = [];
        $originalPath = Storage::disk($file->disk)->path($file->path);

        foreach ($sizes as $name => $dimensions) {
            [$width, $height, $crop] = $dimensions;

            $image = $this->manager->read($originalPath);

            if ($crop) {
                $image->cover($width, $height);
            } else {
                $image->scale(width: $width, height: $height);
            }

            $newPath = 'files/conversions/'.$file->hash.'-'.$name.'.'.$file->extension;
            $absolutePath = Storage::disk($file->disk)->path($newPath);

            // Ensure directory exists
            if (! Storage::disk($file->disk)->exists('files/conversions')) {
                Storage::disk($file->disk)->makeDirectory('files/conversions');
            }

            $image->save($absolutePath, 80); // Default quality 80

            $conversions[$name] = [
                'path' => $newPath,
                'url' => Storage::disk($file->disk)->url($newPath),
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        }

        $file->conversions = $conversions;
        $file->save();
    }

    /**
     * Compress the file.
     */
    public function compress(File $file, int $quality = 80)
    {
        if (! $file->is_image) {
            return;
        }

        $path = Storage::disk($file->disk)->path($file->path);
        $image = $this->manager->read($path);
        $image->save($path, $quality);
        $file->size = filesize($path);
        $file->save();
    }

    /**
     * Set up public sharing.
     */
    public function share(File $file)
    {
        $file->share_token = Str::random(40);
        $file->save();

        return $file->share_token;
    }

    /**
     * Set file visibility (move between disks).
     */
    public function setVisibility(File $file, string $visibility)
    {
        $targetDisk = $visibility === 'public' ? 'public' : 'local';

        if ($file->disk === $targetDisk) {
            return;
        }

        $sourceDisk = $file->disk;
        $paths = [$file->path];

        if ($file->conversions) {
            foreach ($file->conversions as $conversion) {
                $paths[] = $conversion['path'];
            }
        }

        foreach ($paths as $path) {
            $sourcePath = $file->trashed() ? '.trashed/'.$path : $path;
            $targetPath = $sourcePath;

            if (Storage::disk($sourceDisk)->exists($sourcePath)) {
                // Copy to target disk
                Storage::disk($targetDisk)->put(
                    $targetPath,
                    Storage::disk($sourceDisk)->get($sourcePath)
                );
                // Delete from source disk
                Storage::disk($sourceDisk)->delete($sourcePath);
            }
        }

        // Update file model
        $file->disk = $targetDisk;

        // Update conversions URLs
        if ($file->conversions) {
            $conversions = $file->conversions;
            foreach ($conversions as $name => &$conversion) {
                $conversion['url'] = Storage::disk($targetDisk)->url($conversion['path']);
            }
            $file->conversions = $conversions;
        }

        $file->save();
    }

    /**
     * Consume a share token and return the file.
     */
    public function consume(string $token)
    {
        return File::where('share_token', $token)->firstOrFail();
    }

    /**
     * Move the file and its conversions to trash.
     */
    public function moveToTrash(File $file)
    {
        $this->movePhysically($file, true);
    }

    /**
     * Restore the file and its conversions from trash.
     */
    public function restoreFromTrash(File $file)
    {
        $this->movePhysically($file, false);
    }

    /**
     * Delete the file and its conversions physically.
     */
    public function deleteFiles(File $file)
    {
        $storage = Storage::disk($file->disk);

        // Delete main file
        $path = $file->trashed() ? '.trashed/'.$file->path : $file->path;
        if ($storage->exists($path)) {
            $storage->delete($path);
        }

        // Delete conversions
        if ($file->conversions) {
            foreach ($file->conversions as $conversion) {
                $cPath = $file->trashed() ? '.trashed/'.$conversion['path'] : $conversion['path'];
                if ($storage->exists($cPath)) {
                    $storage->delete($cPath);
                }
            }
        }
    }

    /**
     * Move files physically.
     */
    protected function movePhysically(File $file, bool $toTrash)
    {
        $storage = Storage::disk($file->disk);
        $paths = [$file->path];

        if ($file->conversions) {
            foreach ($file->conversions as $conversion) {
                $paths[] = $conversion['path'];
            }
        }

        foreach ($paths as $path) {
            $from = $toTrash ? $path : '.trashed/'.$path;
            $to = $toTrash ? '.trashed/'.$path : $path;

            if ($storage->exists($from)) {
                // Ensure target directory exists
                $targetDir = dirname($to);
                if (! $storage->exists($targetDir)) {
                    $storage->makeDirectory($targetDir);
                }
                $storage->move($from, $to);
            }
        }
    }
}
