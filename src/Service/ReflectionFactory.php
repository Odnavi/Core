<?php

namespace Odnavi\Core\Service;

use ReflectionClass;
use ReflectionProperty;

/**
 * Фабрика ReflectionClass и ReflectionProperty с кэшем per-request.
 * Исключает повторное создание одних и тех же объектов рефлексии.
 */
final class ReflectionFactory
{
    /**
     * Возвращает ReflectionClass для класса или объекта, null если класс не существует.
     *
     * @param object|string $class FQCN или экземпляр класса.
     */
    public static function getClass(object|string $class): ?ReflectionClass
    {
        static $cache = [];

        $instance = is_object($class) ? $class : null;
        $class    = $instance ? get_class($class) : $class;

        if (!isset($cache[$class])) {
            $cache[$class] = $instance
                ? new ReflectionClass($instance)
                : (class_exists($class) ? new ReflectionClass($class) : null);
        }

        return $cache[$class];
    }

    /** Возвращает ReflectionProperty или null, если класс или свойство не существует. */
    public static function getProperty(string $class, string $property): ?ReflectionProperty
    {
        static $cache = [];

        if (!isset($cache[$class][$property])) {
            $ref                      = self::getClass($class);
            $cache[$class][$property] = $ref && $ref->hasProperty($property) ? $ref->getProperty($property) : null;
        }

        return $cache[$class][$property];
    }
}
