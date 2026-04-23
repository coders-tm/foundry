<?php

namespace Foundry\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class BuildTheme implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $themeName;

    protected $themePublic;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($themeName, $themePublic = false)
    {
        $this->themeName = $themeName;
        $this->themePublic = $themePublic;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Run the foundry:theme-build command for the theme
        Artisan::call('foundry:theme-build', [
            'name' => $this->themeName,
            '--theme-public' => $this->themePublic,
            '--path' => str_replace(base_path('/'), '', config('theme.base_path')),
        ]);
    }
}
