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
    use RefreshDatabase, TestsModels;
    
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
- Tip: If your models are directly in `app`, you can skip auto-discovery and pass an array of classes to `assertModels()`:   
`$this->assertModels([User::class, SomethingElse::class, ...]);`

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
- `$this->requiredInstancePerModel[SimpleUser::class] = Model::class;`

## ignoreMethodsPerNamespace
Ignore these methods from validation, useful when packages don't typehint the return type and are failing  
Use `'*'` to ignore all methods in the namespace  
Default: `['Illuminate\\' => ['*']`
- `$this->ignoreMethodsPerNamespace['SomeDude\\Package\\'] = ['dumbMethod'];`

## enableBackRelationValidation
Should back relations (eg User has Post, so Post must have User) be validated  
Default: `true`
- `$this->enableBackRelationValidation = false;`
 
## enableBackRelationTypeValidation
Should back relation return types (eg User HasMany Posts, so Post must BelongTo User) be validated  
Default: `true`
- `$this->enableBackRelationTypeValidation = false;`
 
## skipBackRelationMethodsValidationPerModel
Methods to skip for back relation validation  
Use `'*'` to ignore all methods of the class  
Default: `['App\User' => ['tokens'], 'App\Models\User' => ['tokens']]`
- `$this->skipBackRelationMethodsValidationPerModel[PivotModel::class] = ['*'];`
 