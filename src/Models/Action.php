<?php

namespace Foundry\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['actionable_type', 'actionable_id', 'name'];

    public function actionable()
    {
        return $this->morphTo();
    }
}
