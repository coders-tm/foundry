<?php

namespace Foundry\Traits;

use Foundry\Models\File;

trait Fileable
{
    use HasMorphToOne;

    public function media()
    {
        return $this->morphToMany(File::class, 'fileable')->orderBy('order', 'asc');
    }

    public function avatar()
    {
        return $this->morphToOne(File::class, 'fileable')->wherePivot('type', 'avatar');
    }

    public function thumbnail()
    {
        return $this->morphToOne(File::class, 'fileable');
    }

    public function syncMedia(array $media)
    {
        $files = collect($media)->pluck('id')->filter()->mapWithKeys(function ($item, $key) {
            return [$item => [
                'order' => $key,
            ]];
        });
        $this->media()->sync($files);

        return $this;
    }

    public function detachMedia(array $media)
    {
        if (isset($media['id']) && File::find($media['id'])) {
            return $this->media()->detach($media['id']);
        }

        return false;
    }

    public function attachMedia(array $media)
    {
        if (isset($media['id']) && File::find($media['id'])) {
            return $this->media()->attach($media['id']);
        }

        return false;
    }
}
