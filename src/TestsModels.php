<?php

namespace Paasky\LaravelModelTest;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\IsEqual;
use Roave\BetterReflection\Reflection\ReflectionClass;
use function WyriHaximus\listInstantiatableClassesInDirectory;

/**
 * @mixin Assert
 */
trait TestsModels
{
    /** @var string[] Full path(s) to look in for Model classes*/
    public $modelPaths = ['/path/to/project/app/Models'];

    /** @var string[] Classes that the found Models can be an instance of */
    public $allowedInstances = [Model::class];

    /** @var bool Set to true to skip (rather than fail) classes that are not Models */
    public $allowNonModels = false;

    /** @var string[] Override `allowedInstances` by setting the specific instance required per class */
    public $requiredInstancePerModel = [
        'App\User' => Model::class,
        'App\Models\User' => Model::class,
    ];

    /** @var bool Never set this to true, only used in internal unit tests */
    protected $inSelfTestMode = false;

    /**
     * @param string[] $modelClasses
     * @return void
     */
    public function assertModels(array $modelClasses = []): void
    {
        foreach ($this->modelPaths as $modelPath) {
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
        /** @var Model $class */
        $class = new $className;

        if (isset($this->requiredInstancePerModel[$className])) {
            $requiredInstance = $this->requiredInstancePerModel[$className];
            $this->assertIsTrue(
                $class instanceof $requiredInstance,
                "$className must be instanceof $requiredInstance"
            );
        } else {
            $isClassOneOfAllowedInstances = false;
            foreach ($this->allowedInstances as $allowedInstance) {
                if ($class instanceof $allowedInstance) {
                    $isClassOneOfAllowedInstances = true;
                    break;
                }
            }

            if (!$isClassOneOfAllowedInstances && !$class instanceof Model && $this->allowNonModels) {
                return;
            }

            $this->assertIsTrue(
                $isClassOneOfAllowedInstances,
                "$className must be instanceof " . implode(',', $this->allowedInstances)
            );
        }
    }

    public function assertModelMethods(string $className): void
    {
        foreach (get_class_methods($className) as $methodName) {
            $this->assertModelMethod($className, $methodName);
        }
    }

    public function assertModelMethod(string $className, string $methodName): void
    {
        /** @var Model $class */
        $class = new $className;
        $reflection = ReflectionClass::createFromName($className);

        $method = $reflection->getMethod($methodName);

        // Can only test methods with 0 required params
        if ($method->getNumberOfRequiredParameters() > 0) {
            return;
        }
        $methodReturnValue = $class->{$methodName}();

        // Can only test methods that return a Relation
        if (!$methodReturnValue instanceof Relation) {
            return;
        }

        // Test each relation method works by running `->get()` on it
        try {
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

    protected function assertIsTrue($condition, string $message = ''): void
    {
        if ($this->inSelfTestMode && !$condition) {
            throw new Exception($message ?: "Failed asserting that false is true.");
        } else {
            $this->assertTrue($condition, $message);
        }
    }

    protected function assertIsEqual($expected, $actual, string $message = ''): void
    {
        $isEqual = new IsEqual($expected);
        if ($this->inSelfTestMode && !$isEqual->evaluate($actual)) {
            throw new Exception($message ?: "Failed asserting that {$isEqual->toString()}.");
        } else {
            $this->assertEquals($expected, $actual, $message);
        }
    }
}