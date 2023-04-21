<?php

namespace Paasky\LaravelModelTest\Tests\Models\SubModels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Paasky\LaravelModelTest\Tests\Models\ParentModel;

class SubModel extends Model
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class);
    }
}