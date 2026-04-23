<?php

namespace Foundry\Traits;

use Foundry\Models\Action;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Actionable
{
    public function actions(): MorphMany
    {
        return $this->morphMany(Action::class, 'actionable');
    }

    public function attachAction(string $name): Action
    {
        return $this->actions()->firstOrCreate(compact('name'));
    }

    public function detachAction(string $name): void
    {
        $this->actions()->where('name', $name)->delete();
    }

    public function hasAction(string $name): bool
    {
        return $this->actions()->where('name', $name)->exists();
    }

    public function scopeHasAction($query, string $name): void
    {
        $query->whereHas('actions', fn ($query) => $query->where('name', $name));
    }

    public function scopeDoesntHaveAction($query, string $name): void
    {
        $query->whereDoesntHave('actions', fn ($query) => $query->where('name', $name));
    }

    public function detachActions(): void
    {
        $this->actions()->delete();
    }
}
