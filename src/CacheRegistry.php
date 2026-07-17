<?php

namespace Odnavi\Core;

use Odnavi\Core\Adapter\Cache\NullCache;
use Odnavi\Core\Contract\Cache;
use Odnavi\Core\Service\CacheFactory;

/**
 * Держит активный кэш, который используют ядро и ORM. Приложение регистрирует
 * клиент один раз при инициализации. Пока клиент не задан — используется
 * NullCache (no-op), поэтому кэш остаётся опциональной зависимостью.
 */
final class CacheRegistry
{
    private static ?Cache $cache = null;

    /**
     * Регистрирует активный кэш. Принимает как готовую реализацию Cache, так и
     * «сырой» клиент (Predis, \Redis) — клиент оборачивается в подходящий
     * адаптер автоматически.
     *
     * @param object $client Клиент кэша или готовый Cache.
     */
    public static function set(object $client): void
    {
        self::$cache = CacheFactory::from($client);
    }

    /** Возвращает активный кэш (NullCache, если не задан). */
    public static function get(): Cache
    {
        return self::$cache ??= new NullCache();
    }

    /** Сбрасывает кэш (например, в тестах). */
    public static function reset(): void
    {
        self::$cache = null;
    }
}
