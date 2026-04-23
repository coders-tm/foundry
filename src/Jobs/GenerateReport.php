<?php

namespace Foundry\Jobs;

use Foundry\Models\ReportExport;
use Foundry\Services\Reports\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ReportExport $reportExport
    ) {}

    /**
     * Execute the job - delegate to appropriate service.
     */
    public function handle(): void
    {
        try {
            $this->reportExport->markAsProcessing();

            $fileName = $this->generateFileName();
            $filePath = "reports/{$fileName}";

            // Generate CSV content
            $csv = Writer::createFromFileObject(new \SplTempFileObject);

            // Resolve and execute the appropriate service
            $service = ReportService::resolve($this->reportExport->type);
            $totalRecords = $service->generate($csv, $this->reportExport);

            // Save to storage
            $content = $csv->toString();
            Storage::put($filePath, $content);
            $fileSize = strlen($content);

            // Mark as completed
            $this->reportExport->markAsCompleted($filePath, $fileSize, $totalRecords);
        } catch (\Throwable $e) {
            $this->reportExport->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all supported report types.
     */
    public static function getSupportedTypes(): array
    {
        return ReportService::all();
    }

    /**
     * Check if a report type is supported.
     */
    public static function supportsType(string $type): bool
    {
        return ReportService::has($type);
    }

    /**
     * Generate unique file name.
     */
    protected function generateFileName(): string
    {
        $timestamp = now()->format('Y-m-d_His');

        return "{$this->reportExport->type}_export_{$timestamp}_{$this->reportExport->id}.csv";
    }
}
