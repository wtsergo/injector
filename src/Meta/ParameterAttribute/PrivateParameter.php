<?php

namespace Amp\Injector\Meta\ParameterAttribute;

use Attribute;
use Amp\Injector\Arguments;
use function Amp\Injector\object;

#[Attribute(Attribute::TARGET_PARAMETER)]
class PrivateParameter implements Factory
{
    public function createDefinition(string $class, ?Arguments $arguments = null)
    {
        return object($class, $arguments);
    }
}