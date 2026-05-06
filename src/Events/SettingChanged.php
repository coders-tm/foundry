<?php

/*
 * Created At: 2026-05-06
 * Author: Antigravity
 */

namespace Foundry\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettingChanged
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The setting key.
     */
    public string $key;

    /**
     * The new setting value.
     *
     * @var mixed
     */
    public $value;

    /**
     * Create a new event instance.
     *
     * @param  mixed  $value
     * @return void
     */
    public function __construct(string $key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
}
