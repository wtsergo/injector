<?php

namespace Amp\Injector;

use function Amp\Injector\Internal\normalizeClass;

class AliasResolverImpl implements AliasResolver
{
    /**
     * @var array<string, class-string>
     */
    protected $alias = [];

    public function with(string $alias, string $name): self
    {
        $clone = clone $this;
        if (false !== ($__alias = normalizeClass($alias, false))) {
            $alias = $__alias;
        }
        normalizeClass($name);
        // @phpstan-ignore-next-line
        $clone->alias[$alias] = $name;
        return $clone;
    }

    public function alias(string $id): ?string
    {
        if (false !== ($__id = normalizeClass($id, false))) {
            $id = $__id;
        }
        return $this->alias[$id]??null;
    }

}
