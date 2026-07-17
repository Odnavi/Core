<?php

namespace Odnavi\Core;

use Odnavi\Core\Contract\Connection;
use Odnavi\Core\Service\ConnectionFactory;
use RuntimeException;

/**
 * Держит активное соединение, которое используют репозитории и EntityManager.
 * Приложение регистрирует драйвер один раз при инициализации.
 */
final class ConnectionRegistry
{
    private static ?Connection $connection = null;

    /**
     * Регистрирует активное соединение. Принимает как готовую реализацию
     * Connection, так и «сырой» драйвер (PDO, Doctrine DBAL, wpdb) — драйвер
     * оборачивается в подходящий адаптер автоматически.
     *
     * @param object $driver Драйвер БД или готовый Connection.
     */
    public static function set(object $driver): void
    {
        self::$connection = ConnectionFactory::from($driver);
    }

    /**
     * Возвращает активное соединение.
     *
     * @throws RuntimeException Если соединение не зарегистрировано.
     */
    public static function get(): Connection
    {
        if (self::$connection === null) {
            throw new RuntimeException(
                'ORM: соединение не зарегистрировано. Вызовите ConnectionRegistry::set() при инициализации.'
            );
        }

        return self::$connection;
    }

    /** Сбрасывает соединение (например, в тестах). */
    public static function reset(): void
    {
        self::$connection = null;
    }
}
