<?php

namespace Paasky\LaravelModelTest;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\IsEqual;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use function WyriHaximus\listInstantiatableClassesInDirectory;

/**
 * @mixin Assert
 */
trait TestsModels
{
    /** @var string[] Full path(s) to look in for Model classes, is recursive. */
    public $modelPaths = []; // Defaults to [app_path('Models')]

    /** @var string[] Classes that found classes can be an instance of. */
    public $allowedInstances = [Model::class];

    /** @var bool Set to true to skip (rather than fail) classes that are not Models. */
    public $allowNonModels = false;

    /** @var string[] Override `allowedInstances` by setting the specific instance required per class. */
    public $requiredInstancePerModel = [
        'App\User' => Authenticatable::class,
        'App\Models\User' => Authenticatable::class,
    ];

    /**
     * @var array[] Ignore these methods from validation, useful when packages don't typehint the return type and are failing
     */
    public $ignoreMethodsPerNamespace = [
        'Illuminate\\' => ['*'],
    ];

    /** @var bool Should back relations (related models relate back as well) be validated */
    public $enableBackRelationValidation = true;

    /** @var bool Should back relation return types be validated */
    public $enableBackRelationTypeValidation = true;

    /** @var array[] Methods (* for all) to skip for back relation validation */
    public $skipBackRelationMethodsValidationPerModel = [
        'App\User' => ['tokens'],
        'App\Models\User' => ['tokens'],
    ];

    /** @var bool Never set this to true, only used in internal unit tests. */
    protected $inSelfTestMode = false;

    /** @var array Used internally to keep track of seen relations, to verify all go both ways */
    protected $seenRelations = [];

    /**
     * This is the main function that will find all instantiable classes & test them.
     *
     * @param string[] $modelClasses Override automatic folder-scan with these classes
     * @return void
     */
    public function assertModels(array $modelClasses = []): void
    {
        if ($modelClasses) {
            foreach ($modelClasses as $className) {
                $this->assertModel($className);
            }
        } else {
            foreach ($this->modelPaths ?: [app_path('Models')] as $modelPath) {
                foreach (listInstantiatableClassesInDirectory($modelPath) as $className) {
                    $this->assertModel($className);
                }
            }
        }
        $this->assertBackRelations();
    }

    public function assertModel(string $className): void
    {
        $this->assertModelInstance($className);

        $this->assertModelMethods($className);
    }

    /**
     * Check the Model is an instance of a valid parent-class
     *
     * @param string $className
     * @return void
     * @throws Exception
     */
    public function assertModelInstance(string $className): void
    {
        if (isset($this->requiredInstancePerModel[$className])) {
            $requiredInstance = $this->requiredInstancePerModel[$className];
            $this->assertIsTrue(
                is_subclass_of($className, $requiredInstance),
                "$className must be an instanceof $requiredInstance"
            );
        } else {
            // Skip validation for non-Model classes if allowed
            if (!is_subclass_of($className, Model::class) && $this->allowNonModels) {
                return;
            }

            $isClassOneOfAllowedInstances = false;
            foreach ($this->allowedInstances as $allowedInstance) {
                if (is_subclass_of($className, $allowedInstance)) {
                    $isClassOneOfAllowedInstances = true;
                    break;
                }
            }

            $this->assertIsTrue(
                $isClassOneOfAllowedInstances,
                "$className must be instanceof " . implode(',', $this->allowedInstances)
            );
        }
    }

    public function assertModelMethods(string $className): void
    {
        $classReflection = ReflectionClass::createFromName($className);
        foreach (get_class_methods($className) as $methodName) {
            $this->assertModelMethod($classReflection, $methodName);
        }
    }

    /**
     * If the given method returns a Relation, check it works
     *
     * @param string|ReflectionClass $class
     * @param string $methodName
     * @return void
     * @throws Exception
     */
    public function assertModelMethod($class, string $methodName): void
    {
        $className = is_string($class) ? $class : $class->getName();
        $classReflection = is_string($class) ? ReflectionClass::createFromName($class) : $class;
        $method = $classReflection->getMethod($methodName);

        // Can only test methods with 0 required params
        if ($method->getNumberOfRequiredParameters() > 0) {
            return;
        }

        // Check if the method should be ignored
        foreach ($this->ignoreMethodsPerNamespace as $namespace => $methodsToIgnore) {
            if (str_starts_with($method->getDeclaringClass()->getNamespaceName(), $namespace)) {
                foreach ($methodsToIgnore as $methodToIgnore) {
                    if ($methodName === $methodToIgnore || $methodToIgnore === '*') {
                        return;
                    }
                }
            }
        }

        // Test each relation method works by running `->get()` on it
        try {
            /** @var Model $class */
            $class = new $className;
            $methodReturnValue = $this->getMethodRelation($class, $method);

            // Can only test methods that return a Relation
            if (!$methodReturnValue) {
                return;
            }

            $getOutput = $class->{$methodName}()->get();
            $this->assertIsTrue(
                $getOutput instanceof Collection,
                "$className->$methodName()->get() output needs to be a Collection, was " .
                is_object($getOutput) ? get_class($getOutput) : gettype($getOutput)
            );
        } catch (Exception $e) {
            // Make an assertion that always fails, so we get the method name in the output
            $this->assertIsEqual(
                '',
                "$className->$methodName() is invalid: {$e->getMessage()}",
                "$className->$methodName() is invalid"
            );
        }
    }

    /**
     * Check each seen relation also has a back-relation (eg User HasMany Posts, so Post must BelongTo User)
     *
     * @return void
     */
    public function assertBackRelations(): void
    {
        if (!$this->enableBackRelationValidation) {
            return;
        }

        // Check each class we found relations in
        foreach ($this->seenRelations as $className => $seenRelations) {
            // Should all methods of this class be skipped
            if (in_array('*', $this->skipBackRelationMethodsValidationPerModel[$className] ?? [])) {
                continue;
            }

            foreach ($seenRelations as [$methodName, $relationClassName, $relatedClassName]) {
                // Should this method be skipped
                if (in_array($methodName, $this->skipBackRelationMethodsValidationPerModel[$className] ?? [])) {
                    continue;
                }

                $this->assertIsTrue(
                    $this->wasBackRelationSeen($className, $methodName, $relationClassName, $relatedClassName),
                    "$className->$methodName() relates to $relatedClassName by $relationClassName, but no back relation was seen"
                );
            }
        }
    }

    /**
     * Was the back-relation seen during the earlier @see assertModels()
     *
     * @param string $className Model
     * @param string $methodName
     * @param string $relationClassName Relation
     * @param string $relatedClassName Model
     * @return bool
     * @throws Exception
     */
    protected function wasBackRelationSeen(string $className, string $methodName, string $relationClassName, string $relatedClassName): bool
    {
        foreach ($this->seenRelations as $lookupClassName => $lookupSeenRelations) {
            foreach ($lookupSeenRelations as [$lookupMethodName, $lookupRelationClassName, $lookupRelatedClassName]) {
                // If both relate to each other
                if ($className === $lookupRelatedClassName && $lookupClassName == $relatedClassName) {
                    // If enabled, check the back-relation is appropriate for the orig relation
                    if ($this->enableBackRelationTypeValidation) {

                        $expectedRelationClassNames = $this->getExpectedBackRelationClassNames($relationClassName);

                        if (!$expectedRelationClassNames) {
                            throw new Exception("Unknown relation type $relationClassName in $className->$methodName()");
                        }

                        $this->assertIsTrue(
                            in_array($lookupRelationClassName, $expectedRelationClassNames),
                            "$className->$methodName() returns $relationClassName, but $lookupClassName->$lookupMethodName() does not return " . implode(' or ', $expectedRelationClassNames)
                        );
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Given a Relation-class name, returns an array of expected back-relation classes
     *
     * @param string $relationClassName
     * @return string[]
     */
    protected function getExpectedBackRelationClassNames(string $relationClassName): array
    {
        switch ($relationClassName) {
            case BelongsTo::class:
                return [HasMany::class, HasOne::class, HasOneOrMany::class];
            case HasMany::class:
            case HasOne::class:
            case HasOneOrMany::class:
                return [BelongsTo::class];
            case MorphTo::class:
                return [MorphMany::class, MorphOne::class, MorphOneOrMany::class];
            case MorphMany::class:
            case MorphOne::class:
            case MorphOneOrMany::class:
                return [MorphTo::class];
            case BelongsToMany::class:
                return [BelongsToMany::class];
            case HasManyThrough::class:
            case HasOneThrough::class:
                return [HasManyThrough::class, HasOneThrough::class];
            default:
                return [];
        }
    }

    /**
     * Returns the Relation-class of the class method, null if it's not a Relation
     *
     * @param Model $class
     * @param ReflectionMethod $method
     * @return Relation|null
     */
    protected function getMethodRelation(Model $class, ReflectionMethod $method): ?Relation
    {
        $methodName = $method->getName();

        if ($returnType = $method->getReturnType()) {
            // If the known return type is not a class (getName() doesn't exist)
            // Or the return type name is not a subclass of an Eloquent Relation
            // -> skip the method
            if (!method_exists($returnType, 'getName') ||
                !is_subclass_of($returnType->getName(), Relation::class)
            ) {
                return null;
            }
        }

        $returnValue = $class->{$methodName}();

        // Can only test methods that return a Relation
        if ($returnValue instanceof Relation) {
            if ($this->enableBackRelationValidation) {
                $this->setSeenRelation(
                    get_class($class),
                    $methodName,
                    get_class($returnValue),
                    get_class($returnValue->getRelated())
                );
            }
            return $returnValue;
        }

        return null;
    }

    /**
     * Keep track of seen relations
     *
     * @param string $className Model
     * @param string $methodName
     * @param string $relationClassName Relation
     * @param string $relatedClassName Model
     * @return void
     */
    protected function setSeenRelation(string $className, string $methodName, string $relationClassName, string $relatedClassName)
    {
        $this->seenRelations[$className][] = [$methodName, $relationClassName, $relatedClassName];
    }

    /**
     * Override assertTrue to allow for unit testing
     *
     * @param mixed $condition
     * @param string $message
     * @return void
     * @throws Exception
     */
    protected function assertIsTrue($condition, string $message = ''): void
    {
        if ($this->inSelfTestMode && $condition !== true) {
            throw new Exception($message ?: "Failed asserting that false is true.");
        }
        $this->assertTrue($condition, $message);
    }

    /**
     * Override assertEquals to allow for unit testing
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param string $message
     * @return void
     * @throws Exception
     */
    protected function assertIsEqual($expected, $actual, string $message = ''): void
    {
        if ($this->inSelfTestMode) {
            $isEqual = new IsEqual($expected);
            if (!$isEqual->evaluate($actual)) {
                throw new Exception($message ?: "Failed asserting that {$isEqual->toString()}.");
            }
        }
        $this->assertEquals($expected, $actual, $message);
    }
}