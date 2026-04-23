<?php

namespace Foundry\Commands;

use Foundry\Models\ReportExport;
use Illuminate\Console\Command;

class CleanupExpiredReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:cleanup-expired
                            {--days=7 : Delete reports older than this many days}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired report exports';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $force = $this->option('force');

        $query = ReportExport::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDays($days));

        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired reports found.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("Found {$count} expired report(s). Delete them?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->info("Cleaning up {$count} expired report(s)...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $deleted = 0;
        $query->each(function ($export) use ($bar, &$deleted) {
            $export->delete();
            $deleted++;
            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Successfully deleted {$deleted} expired report(s).");

        return self::SUCCESS;
    }
}
