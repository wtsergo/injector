<?php

namespace Amp\Injector\Composition;

use ArrayIterator;
use MJS\TopSort\Implementations\StringSort;

class CompositionOrdered extends CompositionImpl
{
    /**
     * @param object<string, CompositionItem>|array<string, CompositionItem> $array
     * @param int $flags
     * @param string $iteratorClass
     */
    public function __construct(
        object|array $array = [], int $flags = 0, string $iteratorClass = "ArrayIterator", ?\Closure $sorter=null
    ) {
        if ($sorter === null) {
            $sorter = self::topSort(...);
        }
        $sorted = [];
        $__sorted = $sorter($array);
        foreach ($__sorted as $item) {
            $sorted[] = $item instanceof CompositionItem ? $item->value: $item;
        }
        parent::__construct($sorted, $flags, $iteratorClass);
    }

    static public function selfFactory(
        int $flags = 0, string $iteratorClass = ArrayIterator::class, ?\Closure $sorter=null
    ): \Closure
    {
        static $cache = [];
        if ($sorter === null) {
            $sorter = self::defaultSorter();
        }
        $key = sprintf('%d-%d-%s-%s', spl_object_id($sorter), $flags, $iteratorClass, static::class);
        if (!isset($cache[$key])) {
            $cache[$key] = static function (...$args) use ($sorter, $flags, $iteratorClass) {
                return new static($args, $flags, $iteratorClass, $sorter);
            };
            $cache[$key] = $cache[$key]->bindTo(null, static::class);
        }
        return $cache[$key];
    }

    static public function defaultSorter()
    {
        static $defaultSorter;
        return $defaultSorter ??= self::topSort(...);
    }

    static public function topSort(array $array): array
    {
        foreach ($array as $name => $item) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException(sprintf(
                    '%s $array argument keys must be string', self::class
                ));
            }
            if (!$item instanceof CompositionItem) {
                throw new \InvalidArgumentException(sprintf(
                    '%s array argument values must be instance of %s',
                    self::class, CompositionItem::class
                ));
            }
            foreach ($item->before as $before) {
                if (isset($array[$before])) {
                    $array[$before] = $array[$before]->withDepends([$name]);
                }
            }
            $array[$name] = $array[$name]->withDepends($item->after);
        }
        $sort = new StringSort;
        foreach ($array as $name => $item) {
            $sort->add($name, $item->depends());
        }
        $sortedNames = $sort->sort();
        $sorted = [];
        foreach ($sortedNames as $name) {
            $sorted[] = $array[$name];
        }
        return $sorted;
    }
}
