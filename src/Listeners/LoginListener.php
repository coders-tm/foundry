<?php

namespace Foundry\Listeners;

use Foundry\Models\Admin;
use Foundry\Models\Log;
use Foundry\Models\User;
use Foundry\Notifications\UserLogin;
use Foundry\Services\Helpers;
use Illuminate\Auth\Events\Login;

class LoginListener
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(Login $event)
    {
        /** @var User|Admin $user */
        $user = $event->user;

        if (isset($user->is_active) && ! $user->is_active) {
            return;
        }

        try {
            // Update last_login_at
            $user->update([
                'last_login_at' => now(),
            ]);

            // Create login log
            $loginLog = $user->logs()->create([
                'type' => 'login',
                'options' => Helpers::location(),
            ]);

            // Send notification
            $user->notify(new UserLogin($loginLog));
        } catch (\Throwable $e) {
            $user->logs()->create([
                'type' => 'login-alert',
                'status' => Log::STATUS_ERROR,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
