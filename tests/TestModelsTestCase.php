<?php

namespace Paasky\LaravelModelTest\Tests;

use Illuminate\Database\Eloquent\Model;
use Paasky\LaravelModelTest\Tests\Models\AbstractModel;
use Paasky\LaravelModelTest\Tests\Models\ChildModel;
use Paasky\LaravelModelTest\Tests\Models\ParentModel;
use Paasky\LaravelModelTest\Tests\Models\SubModels\SubModel;
use Paasky\LaravelModelTest\Tests\NotModels\NotModel;
use Paasky\LaravelModelTest\TestsModels;
use PHPUnit\Framework\TestCase;

class TestModelsTestCase extends TestCase
{
    use TestsModels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modelPaths = ['../tests/Models'];
        $this->inSelfTestMode = true;

        // Set defaults
        $this->allowedInstances = [Model::class];
        $this->allowNonModels = false;
        $this->requiredInstancePerModel = [
            'App\User' => Model::class,
            'App\Models\User' => Model::class,
        ];
    }

    public function testAssertModels()
    {
        // Default settings fail at DB connection
        $this->expectExceptionMessage('Call to a member function connection() on null');
        $this->assertModels();

        // Parent & Child pass, but SubModel (in sub-directory) fails
        $this->allowedInstances = [AbstractModel::class];
        $this->requiredInstancePerModel = [ChildModel::class => Model::class];
        $this->expectExceptionMessage(SubModel::class . ' must be instanceof ' . AbstractModel::class);
        $this->assertModels();
    }

    public function testAssertModel()
    {
        // Default settings fail at DB connection
        $this->expectExceptionMessage('Call to a member function connection() on null');
        $this->assertModel(ParentModel::class);

        // NotModel fails
        $this->expectExceptionMessage('Call to a member function connection() on null');
        $this->assertModel(NotModel::class);

        // Allow non-model classes to exist
        $this->allowNonModels = true;
        $this->assertModel(NotModel::class);
    }

    public function testAssertModelInstance()
    {
        // Parent passes, child doesn't (Parent = AbstractModel, Child = Model)
        $this->allowedInstances = [AbstractModel::class];
        $this->assertModelInstance(ParentModel::class);
        $this->expectExceptionMessage(ChildModel::class . ' must be instanceof ' . AbstractModel::class);
        $this->assertModelInstance(ChildModel::class);

        // Both pass (Parent = AbstractModel, Child = Model)
        $this->allowedInstances = [AbstractModel::class];
        $this->requiredInstancePerModel = [ChildModel::class => Model::class];
        $this->assertModelInstance(ParentModel::class);
        $this->assertModelInstance(ChildModel::class);
    }

    public function testAssertModelMethods()
    {
        // Default settings fail at DB connection
        $this->expectExceptionMessage('Call to a member function connection() on null');
        $this->assertModelMethods(SubModel::class);
    }

    public function testAssertModelMethod()
    {
        // Default settings fail at DB connection
        $this->expectExceptionMessage('Call to a member function connection() on null');
        $this->assertModelMethod(ParentModel::class, 'children');

        // Invalid methods are ignored
        $this->assertModelMethod(ParentModel::class, 'methodHasInputParams');
        $this->assertModelMethod(ParentModel::class, 'methodDoesntReturnRelation');
        $this->assertModelMethod(ParentModel::class, 'methodDoesntExist');
    }
}