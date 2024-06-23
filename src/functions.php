<?php

namespace Amp\Injector;

use Amp\Injector\Definition\InjectableFactoryDefinition;
use Amp\Injector\Definition\FactoryDefinition;
use Amp\Injector\Definition\ProviderDefinition;
use Amp\Injector\Definition\SingletonDefinition;
use Amp\Injector\Meta\Reflection\ReflectionConstructorExecutable;
use Amp\Injector\Meta\Reflection\ReflectionFunctionExecutable;
use Amp\Injector\Provider\ValueProvider;
use Amp\Injector\Weaver\AnyWeaver;
use Amp\Injector\Weaver\AutomaticTypeWeaver;
use Amp\Injector\Weaver\NameWeaver;
use Amp\Injector\Weaver\RuntimeTypeWeaver;
use Amp\Injector\Weaver\TypeWeaver;

function definitions(): Definitions
{
    static $definitions = null;

    if (!$definitions) {
        $definitions = new Definitions;
    }

    return $definitions;
}

function arguments(Weaver ...$weavers): Arguments
{
    static $empty = null;

    if (!$empty) {
        $empty = new Arguments;
    }

    $arguments = $empty;

    foreach ($weavers as $weaver) {
        $arguments = $arguments->with($weaver);
    }

    return $arguments;
}

function singleton(Definition $definition): SingletonDefinition
{
    return new SingletonDefinition($definition);
}

function injectableFactory(\Closure $factory, string $class, ?Arguments $arguments = null): InjectableFactoryDefinition
{
    $executable = new ReflectionFunctionExecutable(new \ReflectionFunction($factory));
    $ctorExecutable = new ReflectionConstructorExecutable($class);
    $arguments ??= arguments();

    return new InjectableFactoryDefinition($executable, $ctorExecutable->getParameters(), $arguments);
}

function factory(\Closure $factory, ?Arguments $arguments = null): FactoryDefinition
{
    $executable = new ReflectionFunctionExecutable(new \ReflectionFunction($factory));
    $arguments ??= arguments();

    return new FactoryDefinition($executable, $arguments);
}

function object(string $class, ?Arguments $arguments = null): FactoryDefinition
{
    $executable = new ReflectionConstructorExecutable($class);
    $arguments ??= arguments();

    return new FactoryDefinition($executable, $arguments);
}

function value(mixed $value): Definition
{
    // TODO: Expose type?
    return new ProviderDefinition(new ValueProvider($value));
}

function automaticTypes(Definitions $definitions): AutomaticTypeWeaver
{
    return new AutomaticTypeWeaver($definitions);
}

function runtimeTypes(Definitions $definitions = new Definitions()): RuntimeTypeWeaver
{
    return new RuntimeTypeWeaver($definitions);
}

function names(array $definitions = []): NameWeaver
{
    $names = new NameWeaver;

    foreach ($definitions as $name => $definition) {
        $names = $names->with($name, $definition);
    }

    return $names;
}

function types(): TypeWeaver
{
    return new TypeWeaver;
}

function any(Weaver ...$weavers): AnyWeaver
{
    return new AnyWeaver(...$weavers);
}
