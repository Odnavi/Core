<?php

namespace Odnavi\Core;

use Odnavi\Core\Contract\Db;
use Odnavi\Core\Service\DbFactory;
use RuntimeException;

/**
 * Держит активное соединение, которое используют репозитории и EntityManager.
 * Приложение регистрирует драйвер один раз при инициализации.
 */
final class DbRegistry
{
    private static ?Db $db = null;

    /**
     * Регистрирует активное соединение. Принимает как готовую реализацию
     * Db, так и «сырой» драйвер (PDO, Doctrine DBAL, wpdb) — драйвер
     * оборачивается в подходящий адаптер автоматически.
     *
     * @param object $driver Драйвер БД или готовый Db.
     */
    public static function set(object $driver): void
    {
        self::$db = DbFactory::from($driver);
    }

    /**
     * Возвращает активное соединение.
     *
     * @throws RuntimeException Если соединение не зарегистрировано.
     */
    public static function get(): Db
    {
        if (self::$db === null) {
            throw new RuntimeException(
                'ORM: соединение не зарегистрировано. Вызовите DbRegistry::set() при инициализации.'
            );
        }

        return self::$db;
    }

    /** Сбрасывает соединение (например, в тестах). */
    public static function reset(): void
    {
        self::$db = null;
    }
}
