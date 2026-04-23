<?php

namespace Foundry\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

trait CoreBase
{
    use HasFactory, HasUuids, Logable, SerializeDate;

    public function scopeOnlyActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOnlyInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeOnlyPending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOnlySuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    public function scopeOnlyDeleted($query)
    {
        return $query->where('status', 'deleted');
    }
}
