<?php

namespace Amp\Injector\Weaver;

use Amp\Injector\AliasResolver;
use Amp\Injector\AliasResolverImpl;
use Amp\Injector\Definitions;
use Amp\Injector\InjectionException;
use Amp\Injector\Internal\Reflector;
use Amp\Injector\Meta\Parameter;
use Amp\Injector\Weaver;
use ReflectionAttribute;
use ReflectionClass;
use function Amp\Injector\Internal\getDefaultReflector;
use Amp\Injector\Definition;
use function Amp\Injector\Internal\normalizeClass;
use Amp\Injector\Meta\ParameterAttribute;
use function Amp\Injector\object;
use function Amp\Injector\singleton;
use function Amp\Injector\proxy;

class RuntimeTypeWeaver implements Weaver
{
    /** @var \Closure(string): (string|null) $alias */
    private \Closure $alias;

    public function __construct(
        private Definitions $runtimeDefinitions = new Definitions(),
        private AliasResolver $aliasResolver = new AliasResolverImpl()
    ) {
        $this->alias = $this->aliasResolver->alias(...);
    }

    /**
     * @param string $class
     * @param string $parameterName
     * @return non-empty-string
     */
    public static function parameterKey(string $class, string $parameterName): string
    {
        $nClass = normalizeClass($class);
        return $nClass.'::'.$parameterName;
    }

    public function getDefinition(int|string|Parameter $parameter): ?Definition
    {
        if (is_scalar($parameter)) {
            throw new InjectionException('int|string parameter is not supported');
        }
        if (($type = $parameter->getType()) && $parameter->getDeclaringClass()) {
            $class = $parameter->getDeclaringClass();
            $key = self::parameterKey($class, $parameter->getName());
            if ($this->runtimeDefinitions->get($key)) {
                return $this->runtimeDefinitions->get($key);
            }
            $injectorAttribute = null;
            if ($parameter->hasAttribute(
                ParameterAttribute::class,
                ReflectionAttribute::IS_INSTANCEOF
            )) {
                $injectorAttribute = $parameter->getAttribute(
                    ParameterAttribute::class,
                    ReflectionAttribute::IS_INSTANCEOF
                );
            }
            if (!$injectorAttribute) {
                return null;
            }
            if ($injectorAttribute instanceof ParameterAttribute\FactoryParameter) {
                $this->processFactoryParameter($injectorAttribute, $key);
                return $this->runtimeDefinitions->get($key);
            }
            if ($injectorAttribute instanceof ParameterAttribute\ProxyParameter) {
                // TODO: find 8.1 compatible proxy manager 3rd-party
                throw new InjectionException('Proxy not supported yet');
                /*$this->processParameterAttribute($injectorAttribute, $injectorAttribute->class, $key);
                return $this->runtimeDefinitions->get($key);*/
            }
            foreach ($type->getTypes() as $type) {
                /** @var class-string $type */
                $type = $this->resolveType($type);
                $typeReflection = null;
                try {
                    $typeReflection = new ReflectionClass($type);
                    // @phpstan-ignore-next-line
                } catch (\ReflectionException $e) {}
                // @phpstan-ignore-next-line
                if ($typeReflection && $typeReflection->isInstantiable()) {
                    $this->processParameterAttribute($injectorAttribute, $type, $key);
                    if ($this->runtimeDefinitions->get($key)) {
                        return $this->runtimeDefinitions->get($key);
                    }
                }
            }
        }

        return null;
    }

    private function resolveType(string $type): string
    {
        return ($this->alias)($type) ?? $type;
    }

    protected function factoryId(string $class): string
    {
        return $this->resolveType($class).'\0factory';
    }

    protected function proxyId(string $class): string
    {
        return $this->resolveType($class).'\0proxy';
    }

    /**
     * @param ParameterAttribute $parameterAttribute
     * @param string $key
     * @return void
     */
    protected function processFactoryParameter(ParameterAttribute $parameterAttribute, string $key): void
    {
        if (!$this->runtimeDefinitions->get($key)) {
            if ($parameterAttribute instanceof ParameterAttribute\FactoryParameter) {
                $factoryId = $this->factoryId($parameterAttribute->class);
                $definition = $this->runtimeDefinitions->get($factoryId);
                if (!$definition) {
                    $definition = $parameterAttribute->createDefinition($this->alias);
                    $this->addDefinition($definition, $factoryId);
                }
                $this->addDefinition($definition, $key);
            }
        }
    }

    /**
     * @param ParameterAttribute $parameterAttribute
     * @param class-string $targetClass
     * @param string $key
     * @return void
     */
    protected function processParameterAttribute(
        ParameterAttribute $parameterAttribute, string $targetClass, string $key
    ): void
    {
        if (!$this->runtimeDefinitions->get($key)) {
            if ($parameterAttribute instanceof ParameterAttribute\Service) {
                $definition = $this->runtimeDefinitions->get($targetClass);
                if (!$definition) {
                    if ($parameterAttribute instanceof ParameterAttribute\Factory) {
                        $definition = $parameterAttribute->createDefinition($targetClass, $this->alias);
                    } else {
                        $definition = singleton(object($targetClass));
                    }
                    $this->addDefinition($definition, $targetClass);
                }
                if ($parameterAttribute instanceof ParameterAttribute\ProxyParameter) {
                    $proxyId = $this->proxyId($targetClass);
                    $proxyDefinition = $this->runtimeDefinitions->get($proxyId);
                    if (!$proxyDefinition) {
                        $proxyDefinition = proxy($targetClass, $definition);
                        $this->addDefinition($proxyDefinition, $proxyId);
                    }
                }
                $this->addDefinition($proxyDefinition ?? $definition, $key);
            } elseif ($parameterAttribute instanceof ParameterAttribute\Factory) {
                $definition = $parameterAttribute->createDefinition($targetClass, $this->alias);
                $this->addDefinition($definition, $key);
            }
        }
    }

    protected function addDefinition(Definition $definition, string $id): RuntimeTypeWeaver
    {
        $this->runtimeDefinitions = $this->runtimeDefinitions->with($definition, $id);
        return $this;
    }
}
