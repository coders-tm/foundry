<?php

namespace Foundry\Listeners;

use Foundry\Models\Admin;
use Foundry\Models\User;
use Foundry\Services\Helpers;
use Illuminate\Auth\Events\Logout;

class LogoutListener
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(Logout $event)
    {
        /** @var User|Admin $user */
        $user = $event->user;

        if ($user) {
            $user->logs()->create([
                'type' => 'logout',
                'options' => Helpers::location(),
            ]);
        }
    }
}
