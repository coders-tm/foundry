<?php

namespace Foundry\Services\Theme;

use Illuminate\Support\Facades\File;

class FileMeta
{
    protected $file;

    protected $basepath;

    protected $prefix;

    public function __construct($file, $basepath, $prefix = null)
    {
        $this->file = $file;
        $this->basepath = $basepath;
        $this->prefix = $prefix;
    }

    public function toArray()
    {
        $fileInfo = [
            'header' => 'file',
        ];

        $basepath = str_replace($this->basepath.'/', '', $this->file->getPathname());

        if ($this->prefix) {
            $basepath = $this->prefix.'/'.$basepath;
        }

        // Basic info
        $fileInfo['name'] = basename($this->file->getPathname());
        $fileInfo['basepath'] = $basepath;
        $fileInfo['size'] = $this->getSize();

        $fileInfo['modified_at'] = $this->getModifiedAt();
        $fileInfo['mimetype'] = $this->getMimeType();
        $fileInfo['icon'] = $this->getIcon();

        // Additional info for images
        if ($this->isImage()) {
            $fileInfo['dimensions'] = $this->getImageDimensions();
        }

        return $fileInfo;
    }

    protected function getSize()
    {
        return File::size($this->file);
    }

    protected function getModifiedAt()
    {
        return date('Y-m-d H:i:s', File::lastModified($this->file));
    }

    protected function getMimeType()
    {
        return File::mimeType($this->file);
    }

    protected function getIcon()
    {
        $mimeType = $this->getMimeType();
        $icon = 'fas fa-code'; // Default icon for files

        if (str_starts_with($mimeType, 'image/')) {
            $icon = 'fas fa-image'; // Icon for image files
        }

        return $icon;
    }

    protected function isImage()
    {
        $mimeType = $this->getMimeType();

        return str_starts_with($mimeType, 'image/');
    }

    protected function getImageDimensions()
    {
        if ($this->isImage()) {
            [$width, $height] = getimagesize($this->file);

            return [
                'width' => $width,
                'height' => $height,
            ];
        }

        return null;
    }
}
