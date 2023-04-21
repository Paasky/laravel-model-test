# paasky/laravel-model-test
Trait to test that all your Laravel Models are instances of correct classes and all relations work

# Installation
`composer require paasky/laravel-model-test --dev`

# Usage
In any PHPUnit test
```
use Paasky\TestsModels;

class MyTests extends TestCase
{
    use TestsModels;
    
    public function testModels()
    {
        $this->assertModels();
    }
}
```

# Configuration

Configurations are public attributes of the TestsModels-Trait

## modelPaths
Full path(s) to look in for Model classes, includes sub-folders.  
Default: `[app_path('Models')]`
- `$this->modelPaths = [app_path('Models'), app_path('SuperCoolModels')];`

## allowedInstances
Classes that found classes can be an instance of.  
Default: `[Model::class]`
- `$this->allowedInstances = [ProjectModel::class];`

## allowNonModels
Skip or fail classes that are not instances of `Illuminate\Database\Eloquent\Model`  
Default: `false` (Fail)
- `$this->allowNonModels = true;`

## requiredInstancePerModel
Override `allowedInstances` for specific classes by setting the required instance.  
Default: `['App\User' => Authenticatable::class, 'App\Models\User' => Authenticatable::class]`
- `$this->requiredInstancePerModel = [SimpleUser::class => Model::class];`