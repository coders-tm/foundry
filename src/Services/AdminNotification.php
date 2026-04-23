<?php

namespace Foundry\Services;

use Foundry\Notifications\BaseNotification;
use Illuminate\Support\Facades\Notification;

class AdminNotification
{
    public function __invoke(BaseNotification $notification)
    {
        return Notification::route('mail', [
            config('foundry.admin_email') => config('app.name'),
        ])->notify($notification);
    }
}
