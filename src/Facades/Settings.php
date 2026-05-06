<?php

/*
 * Created At: 2026-05-06
 * Author: Antigravity
 */

namespace Foundry\Facades;

use Foundry\Services\SettingsService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, $default = null)
 * @method static void set(string|array $key, $value = null)
 * @method static void load()
 * @method static void syncConfig()
 * @method static array all()
 *
 * @see SettingsService
 */
class Settings extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'settings';
    }
}
