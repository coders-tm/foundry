<?php

namespace Foundry\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LangParseCommand extends Command
{
    protected $signature = 'lang:parse';

    protected $description = 'Parse PHP files and update language files with new translation keys';

    protected $langDirPath;

    protected $phpDirectories;

    public function __construct()
    {
        parent::__construct();
        $this->langDirPath = resource_path('lang');
        $this->phpDirectories = [
            app_path('**/*.php'),
            resource_path('views/**/*.php'),
        ];
    }

    public function handle()
    {
        foreach ($this->phpDirectories as $pattern) {
            $this->parseFiles($pattern);
        }
    }

    protected function findTranslationKeys($content)
    {
        preg_match_all("/__\('([^']+)'\)/", $content, $matches);

        return array_unique($matches[1]);
    }

    protected function updateLangFiles($keys)
    {
        $langFiles = File::glob($this->langDirPath.'/*.json');
        foreach ($langFiles as $langFile) {
            $langData = json_decode(File::get($langFile), true);
            $updated = false;
            foreach ($keys as $key) {
                if (! isset($langData[$key])) {
                    $langData[$key] = $key;
                    $updated = true;
                }
            }
            if ($updated) {
                File::put($langFile, json_encode($langData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
    }

    protected function parseFiles($pattern)
    {
        $files = File::glob($pattern);
        foreach ($files as $file) {
            $content = File::get($file);
            $keys = $this->findTranslationKeys($content);
            $this->updateLangFiles($keys);
        }
    }
}
