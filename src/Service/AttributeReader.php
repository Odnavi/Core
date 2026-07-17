<?php

namespace Odnavi\Core\Service;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Обёртка над нативными PHP 8 #[Attribute] с кэшем результатов на request.
 */
final class AttributeReader
{
    /**
     * Свойства класса с атрибутами, отсортированные по иерархии (родители первыми).
     *
     * @param int $filter ReflectionProperty::IS_* флаги
     * @return array<array{property: ReflectionProperty, attrs: object[]}>
     */
    public static function getForProperties(ReflectionClass $class, int $filter = ReflectionProperty::IS_PROTECTED): array
    {
        static $cache = [];

        $key = $class->getName() . '_' . $filter;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $result = [];
        foreach (self::getSortedProperties($class, $filter) as $property) {
            $attrs    = array_map(fn($attribute) => $attribute->newInstance(), $property->getAttributes());
            $result[] = ['property' => $property, 'attrs' => $attrs];
        }

        return $cache[$key] = $result;
    }

    /**
     * Методы класса с атрибутами, без статических.
     *
     * @param int $filter ReflectionMethod::IS_* флаги
     * @param string|string[]|null $attributeClass FQCN атрибута(ов), которые нужно отдать; null — все атрибуты
     * @return array<array{method: ReflectionMethod, attrs: object[]}>
     */
    public static function getForMethods(ReflectionClass $class, int $filter = ReflectionMethod::IS_PUBLIC, string|array|null $attributeClass = null): array
    {
        static $cache = [];

        $classes = (array) $attributeClass;
        $key     = $class->getName() . '_' . $filter . '_' . implode(',', $classes);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $result = [];
        foreach ($class->getMethods($filter) as $method) {
            if ($method->isStatic()) {
                continue;
            }

            $result[] = ['method' => $method, 'attrs' => self::instantiateAttributes($method, $classes)];
        }

        return $cache[$key] = $result;
    }

    /**
     * Атрибуты уровня класса.
     *
     * @param string|string[]|null $attributeClass FQCN атрибута(ов), которые нужно отдать; null — все атрибуты
     * @return object[]
     */
    public static function getForClass(ReflectionClass $class, string|array|null $attributeClass = null): array
    {
        static $cache = [];

        $classes = (array) $attributeClass;
        $key     = $class->getName() . '_' . implode(',', $classes);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $attrs = self::instantiateAttributes($class, $classes);

        return $cache[$key] = $attrs;
    }

    /**
     * Создаёт экземпляры атрибутов рефлектируемого элемента, при необходимости отфильтрованные по FQCN.
     *
     * @param ReflectionClass|ReflectionMethod|ReflectionProperty $reflection
     * @param string[] $classes FQCN атрибутов; пустой массив — все атрибуты
     * @return object[]
     */
    private static function instantiateAttributes($reflection, array $classes): array
    {
        if (!$classes) {
            return array_map(fn($attribute) => $attribute->newInstance(), $reflection->getAttributes());
        }

        $attrs = [];
        foreach ($classes as $name) {
            foreach ($reflection->getAttributes($name) as $attribute) {
                $attrs[] = $attribute->newInstance();
            }
        }

        return $attrs;
    }

    /**
     * Свойства класса без статических, родители первыми.
     *
     * @param int $filter ReflectionProperty::IS_* флаги
     * @return ReflectionProperty[]
     */
    private static function getSortedProperties(ReflectionClass $class, int $filter): array
    {
        $sortOrder   = [];
        $parentClass = $class->getParentClass();
        while ($parentClass !== false) {
            foreach ($parentClass->getProperties($filter) as $p) {
                $sortOrder[$p->getName()] = true;
            }
            $parentClass = $parentClass->getParentClass();
        }

        $properties = array_filter(
            $class->getProperties($filter),
            fn($p) => !$p->isStatic()
        );

        usort($properties, function (ReflectionProperty $a, ReflectionProperty $b) use ($sortOrder) {
            return isset($sortOrder[$a->getName()]) === isset($sortOrder[$b->getName()])
                ? 0
                : (isset($sortOrder[$a->getName()]) ? -1 : 1);
        });

        return array_values($properties);
    }
}
