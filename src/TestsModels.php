<?php

namespace Paasky\LaravelModelTest;

use Exception;
use Illuminate\Database\Eloquent\Model;
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

    /** @var bool Never set this to true, only used in internal unit tests. */
    protected $inSelfTestMode = false;

    /**
     * This is the main function that will find all instantiable classes & test them.
     *
     * @param string[] $modelClasses Override automatic folder-scan with these classes
     * @return void
     */
    public function assertModels(array $modelClasses = []): void
    {
        foreach ($this->modelPaths ?: [app_path('Models')] as $modelPath) {
            $classNames = $modelClasses ?: listInstantiatableClassesInDirectory($modelPath);

            foreach ($classNames as $className) {
                $this->assertModel($className);
            }
        }
    }

    public function assertModel(string $className): void
    {
        $this->assertModelInstance($className);

        $this->assertModelMethods($className);
    }

    public function assertModelInstance(string $className): void
    {
        // Skip validation for non-Model classes if allowed
        if (!is_subclass_of($className, Model::class) && $this->allowNonModels) {
            return;
        }

        if (isset($this->requiredInstancePerModel[$className])) {
            $requiredInstance = $this->requiredInstancePerModel[$className];
            $this->assertIsTrue(
                is_subclass_of($className, $requiredInstance),
                "$className must be an instanceof $requiredInstance"
            );
        } else {
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

    protected function getMethodRelation(Model $class, ReflectionMethod $method): ?Relation
    {
        $methodName = $method->getName();

        // Ignore all methods defined within Illuminate
        if (str_starts_with($method->getDeclaringClass()->getNamespaceName(), 'Illuminate\\')) {
            return null;
        }

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
        if (!$returnValue instanceof Relation) {
            return null;
        }
        return $returnValue;
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