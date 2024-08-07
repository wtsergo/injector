<?php

namespace Amp\Injector\Internal;

/** @internal */
function normalizeClass(string $class, bool $throw = true): string|bool
{
    static $cache = [];

    if (isset($cache[$class])) {
        return $cache[$class];
    }

    // See https://www.php.net/manual/en/language.oop5.basic.php
    if (!\preg_match('(^\\\\?(?:[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*\\\\)*[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$)', $class)) {
        if ($throw) {
            throw new \Error('Invalid class name: ' . $class);
        } else {
            return false;
        }
    }

    $normalizedClass = \strtolower(\ltrim($class, '\\'));

    $cache[$class] = $normalizedClass;
    // TODO: Limit cache size?

    return $normalizedClass;
}

/** @internal */
function getDefaultReflector(): Reflector
{
    static $reflector = null;

    if (!$reflector) {
        $reflector = new CachingReflector(new StandardReflector);
    }

    return $reflector;
}
