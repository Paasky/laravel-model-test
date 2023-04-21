<?php

namespace Paasky\LaravelModelTest\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildModel extends Model
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChildModel::class);
    }
}