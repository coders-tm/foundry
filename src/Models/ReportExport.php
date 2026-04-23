<?php

namespace Foundry\Models;

use Foundry\Database\Factories\ReportExportFactory;
use Foundry\Foundry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReportExport extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_id',
        'type',
        'status',
        'file_name',
        'file_path',
        'file_size',
        'total_records',
        'filters',
        'metadata',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'filters' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the admin that requested the export.
     */
    public function admin()
    {
        return $this->belongsTo(Foundry::$adminModel);
    }

    /**
     * Check if the export is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the export is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the export is pending or processing.
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Check if the file exists.
     */
    public function fileExists(): bool
    {
        return $this->file_path && Storage::exists($this->file_path);
    }

    /**
     * Get the full URL for the file.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }

    /**
     * Mark export as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark export as completed.
     */
    public function markAsCompleted(string $filePath, int $fileSize, int $totalRecords): void
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'total_records' => $totalRecords,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7), // Files expire after 7 days
        ]);
    }

    /**
     * Mark export as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Delete the associated file.
     */
    public function deleteFile(): bool
    {
        if ($this->file_path && Storage::exists($this->file_path)) {
            return Storage::delete($this->file_path);
        }

        return false;
    }

    /**
     * Scope to get exports for a specific admin.
     *
     * @param  Builder  $query
     * @param  int|string  $adminId
     * @return Builder
     */
    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    /**
     * Scope to get exports by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get exports by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get expired exports.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
            ->whereNotNull('expires_at');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Delete file when model is deleted
        static::deleting(function ($export) {
            $export->deleteFile();
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReportExportFactory
    {
        return ReportExportFactory::new();
    }
}
