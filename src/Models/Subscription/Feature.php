<?php

namespace Foundry\Models\Subscription;

use Foundry\Database\Factories\FeatureFactory;
use Foundry\Traits\SerializeDate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory, HasUuids, SerializeDate;

    protected $table = 'features';

    protected $fillable = [
        'label',
        'slug',
        'type',
        'resetable',
        'description',
    ];

    protected $casts = [
        'resetable' => 'boolean',
    ];

    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    public static function findBySlug($slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return FeatureFactory::new();
    }
}
