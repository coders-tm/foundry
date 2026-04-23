<?php

namespace Foundry\Models\Blog;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['message'];

    protected $hidden = [
        'commentable_type',
        'commentable_id',
        'userable_type',
        'userable_id',
        'status',
    ];

    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->morphTo('user', 'userable_type', 'userable_id')->withOnly([]);
    }

    public function replies()
    {
        return $this->morphMany(static::class, 'commentable');
    }
}
