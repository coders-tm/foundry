<?php

namespace Foundry\Exports;

use Foundry\Models\Group;
use Illuminate\Support\Collection;

class GroupExport extends BaseExport
{
    public function getData(): Collection
    {
        return Group::all()->map(function ($group) {
            return [
                'Name' => $group->name,
                'Description' => $group->description,
                'Modules' => $group->modules->pluck('name')->implode(', '),
                'Created At' => $group->created_at->toDateTimeString(),
            ];
        });
    }

    public function getHeadings(): array
    {
        return [
            'Name',
            'Description',
            'Modules',
            'Created At',
        ];
    }
}
