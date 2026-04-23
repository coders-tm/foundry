<?php

namespace Foundry\Models;

use Foundry\Services\FileManagerService;
use Foundry\Traits\Core;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class File extends Model
{
    use Core;

    public static $route = 'files.download';

    protected $file;

    protected $fillable = [
        'disk',
        'path',
        'original_file_name',
        'hash',
        'mime_type',
        'extension',
        'size',
        'ref',
        'alt',
        'conversions',
        'share_token',
    ];

    protected $appends = [
        'name',
        'is_image',
        'is_pdf',
        'icon',
        'shared_url',
        'is_public',
    ];

    protected $logIgnore = [
        'conversions',
    ];

    protected $casts = [
        'is_embed' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->attributes['disk'] = isset($attributes['disk']) ? $attributes['disk'] : config('filesystems.default');
    }

    public function setHttpFile($file)
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', '7z', 'rar', 'csv', 'mp4', 'mov', 'avi', 'wmv', 'webm', 'ogg'];

        // 1. Validate Extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $allowedExtensions)) {
            throw new \Exception("File type not allowed ($extension).");
        }

        // 2. Validate MIME Type (basic check against extension)
        $mime = $file->getMimeType();
        if ($this->isDangerousMime($mime)) {
            throw new \Exception('File Type not allowed (Dangerous content detected).');
        }

        $this->file = $file;
        $this->original_file_name = $file->getClientOriginalName();
        // Sanitize filename to prevent directory traversal or shell special chars
        $this->original_file_name = basename($this->original_file_name);

        $this->hash = md5_file($file->getRealPath());
        $this->mime_type = $mime;
        $this->extension = $extension;
        $this->size = $file->getSize();
    }

    protected function isDangerousMime($mime)
    {
        return Str::contains($mime, ['php', 'application/x-httpd-php', 'application/x-php', 'text/html', 'application/x-msdownload', 'application/x-sh', 'application/x-batch', 'application/x-executable']);
    }

    public function save($options = [])
    {
        if ($this->file) {
            $this->path = $this->file->storeAs('files', $this->hash.'.'.$this->extension, $this->disk);
            if ($this->disk == 's3') {
                $this->url = Storage::disk($this->disk)->url($this->path);
            }
        }

        return parent::save($options);
    }

    public function modify($options = [])
    {
        if ($this->file) {
            $this->path = $this->file->storeAs('public', $this->path);
        }

        return parent::update($options);
    }

    protected static function booted()
    {
        static::deleted(function (File $file) {
            if ($file->isForceDeleting()) {
                app(FileManagerService::class)->deleteFiles($file);
            } else {
                app(FileManagerService::class)->moveToTrash($file);
            }
        });

        static::restoring(function (File $file) {
            app(FileManagerService::class)->restoreFromTrash($file);
        });
    }

    public function fileable()
    {
        return $this->morphTo();
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // If it's a local disk and we are in admin context (or matching condition), use the proxy route
                if ($this->disk === 'local' && request()->is('admin/*')) {
                    return $this->withVersion(route('admin.files.download', $this->id));
                }

                if ($this->trashed()) {
                    return $this->withVersion(Storage::disk($this->disk)->url('.trashed/'.$this->path));
                }

                return $this->withVersion($value ?: Storage::disk($this->disk)->url($this->path));
            },
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->original_file_name,
        );
    }

    protected function isImage(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::contains($this->mime_type, 'image') && ! $this->is_embed,
        );
    }

    protected function isPdf(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::contains($this->mime_type, 'pdf'),
        );
    }

    protected function icon(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->fileType($this->original_file_name),
        );
    }

    protected function sharedUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->share_token ? route('files.share', $this->share_token) : null,
        );
    }

    protected function isPublic(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->disk === 'public',
        );
    }

    protected function conversions(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $data = is_array($value) ? $value : json_decode($value ?? 'null', true);

                if (! $data) {
                    return $data;
                }

                return collect($data)
                    ->map(fn ($conversion, $size) => array_merge($conversion, ['url' => $this->buildConversionUrl($size, $conversion)]))
                    ->all();
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    /**
     * Get conversion URL by size.
     */
    public function getConversionUrl(string $size): ?string
    {
        $data = is_array($this->conversions) ? $this->conversions : [];

        return isset($data[$size]) ? $data[$size]['url'] : null;
    }

    /**
     * Build the versioned URL for a single conversion entry.
     */
    private function buildConversionUrl(string $size, array $conversion): ?string
    {
        $path = $conversion['path'] ?? null;

        if (! $path) {
            return null;
        }

        if ($this->disk === 'local' && request()->is('admin/*')) {
            return $this->withVersion(route('admin.files.download', ['file' => $this->id, 'conversion' => $size]));
        }

        if ($this->trashed()) {
            return $this->withVersion(Storage::disk($this->disk)->url('.trashed/'.$path));
        }

        return $this->withVersion($conversion['url'] ?? Storage::disk($this->disk)->url($path));
    }

    /**
     * Append a cache-busting ?v={timestamp} query parameter to a URL.
     */
    private function withVersion(?string $url): ?string
    {
        if (! $url) {
            return $url;
        }

        $timestamp = $this->updated_at?->timestamp ?? $this->created_at?->timestamp ?? time();
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.$timestamp;
    }

    protected function fileType($file_name)
    {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'pdf':
                $type = 'pdf';
                break;
            case 'docx':
            case 'doc':
                $type = 'word';
                break;
            case 'xls':
            case 'xlsx':
                $type = 'excel';
                break;
            case 'mp3':
            case 'ogg':
            case 'wav':
                $type = 'audio';
                break;
            case 'mp4':
            case 'mov':
            case 'avi':
            case 'wmv':
            case 'webm':
                $type = 'video';
                break;
            case 'zip':
            case '7z':
            case 'rar':
                $type = 'archive';
                break;
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
            case 'svg':
                $type = 'image';
                break;
            default:
                $type = 'alt';
        }

        return $type;
    }

    public function path()
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public static function findByHash(string $hash)
    {
        return static::where('hash', $hash)->firstOrFail();
    }
}
