<?php

namespace Foundry\Exports;

use Foundry\Models\Admin;
use Illuminate\Support\Collection;

class AdminExport extends BaseExport
{
    public function getData(): Collection
    {
        return Admin::all()->map(function ($admin) {
            return [
                'Name' => "{$admin->first_name} {$admin->last_name}",
                'Email' => $admin->email,
                'Phone' => $admin->phone_number,
                'Status' => $admin->is_active ? 'Active' : 'Inactive',
                'Roles' => $admin->groups->pluck('name')->implode(', '),
                'Created At' => $admin->created_at->toDateTimeString(),
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
            'Roles',
            'Created At',
        ];
    }
}
