<?php

namespace Paasky\LaravelModelTest\Tests\Models;

class ParentModel extends AbstractModel
{
    /**
     * Keep return type ambiguous to test return-type check works properly
     */
    public function children()
    {
        return time() > 0 ? $this->hasMany(ChildModel::class) : false;
    }

    public function methodHasInputParams($input): string
    {
        return "$input input";
    }

    public function methodDoesntReturnRelation()
    {
        return time() > 123456 ? "text" : false;
    }
}