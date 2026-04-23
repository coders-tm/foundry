<?php

namespace Foundry\Exports;

use App\Models\User;
use Illuminate\Support\Collection;

class UserExport extends BaseExport
{
    public function getData(): Collection
    {
        return User::all()->map(function ($user) {
            return [
                'Name' => "{$user->first_name} {$user->last_name}",
                'Email' => $user->email,
                'Phone' => $user->phone_number,
                'Status' => $user->status,
                'Created At' => $user->created_at->toDateTimeString(),
            ];
        });
    }

    public function getHeadings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Status',
            'Created At',
        ];
    }
}
