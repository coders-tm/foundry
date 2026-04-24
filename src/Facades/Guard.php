<?php

namespace Foundry\Facades;

use Foundry\Services\GuardManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string current(\Illuminate\Http\Request|null $request = null)
 * @method static string key(\Illuminate\Http\Request|null $request = null)
 * @method static string context()
 * @method static \Foundry\Services\GuardManager setRequest(\Illuminate\Http\Request $request)
 * @method static \Illuminate\Http\Request getRequest()
 * @method static \Foundry\Services\GuardManager forgetResolved()
 * @method static \Foundry\Services\GuardManager resolveUsing(\Closure|null $callback)
 * @method static bool is(string $name)
 * @method static string guard(\Illuminate\Http\Request|null $request = null)
 * @method static bool isAdmin(\Illuminate\Http\Request|null $request = null)
 * @method static string passwordBroker(\Illuminate\Http\Request|null $request = null)
 * @method static string home(\Illuminate\Http\Request|null $request = null)
 * @method static string loginRoute(\Illuminate\Http\Request|null $request = null)
 * @method static string twoFactorRoute(\Illuminate\Http\Request|null $request = null)
 * @method static string passwordResetRoute(\Illuminate\Http\Request|null $request = null)
 * @method static string prefix(\Illuminate\Http\Request|null $request = null)
 *
 * @see GuardManager
 */
class Guard extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return GuardManager::class;
    }
}
