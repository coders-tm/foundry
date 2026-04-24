<?php

namespace Foundry\Models;

use Foundry\Concerns\SerializeDate;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Module extends Model
{
    use HasSlug, SerializeDate;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'name',
        'url',
        'show_menu',
        'sort_order',
    ];

    protected $casts = [
        'show_menu' => 'boolean',
    ];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('key');
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'module_key', 'key');
    }
}
