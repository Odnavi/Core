<?php

namespace Odnavi\Core;

use Odnavi\Core\Contract\Db;
use Odnavi\Core\Service\DbFactory;
use RuntimeException;

/**
 * Держит соединения, которые используют репозитории и EntityManager.
 *
 * Активное соединение одно и меняется вместе с приложением (AppContext::switch).
 * Помимо него регистрируются именованные — базы, общие для всех приложений
 * (идентичность в `base`). Соединение выбирает репозиторий свойством
 * `$connection`, поэтому пакет users пишет в base независимо от того, под
 * каким приложением идёт запрос, а сам пакет о базе ничего не знает.
 */
final class DbRegistry
{
    private static ?Db $db = null;

    /** @var array<string, Db> Именованные соединения, не зависящие от приложения. */
    private static array $named = [];

    /**
     * Регистрирует соединение. Принимает как готовую реализацию Db, так и
     * «сырой» драйвер (PDO, Doctrine DBAL, wpdb) — драйвер оборачивается
     * в подходящий адаптер автоматически.
     *
     * @param object  $driver Драйвер БД или готовый Db.
     * @param ?string $name   Имя соединения; null — активное соединение приложения.
     */
    public static function set(object $driver, ?string $name = null): void
    {
        $db = DbFactory::from($driver);

        if ($name === null) {
            self::$db = $db;
            return;
        }

        self::$named[$name] = $db;
    }

    /**
     * Возвращает соединение по имени; без имени — активное.
     *
     * @param ?string $name Имя именованного соединения.
     *
     * @throws RuntimeException Если соединение не зарегистрировано.
     */
    public static function get(?string $name = null): Db
    {
        if ($name !== null) {
            return self::$named[$name] ?? throw new RuntimeException(
                "Соединение '$name' не зарегистрировано. "
                . "Вызовите DbRegistry::set(\$driver, '$name') при инициализации."
            );
        }

        if (self::$db === null) {
            throw new RuntimeException(
                'Соединение не зарегистрировано. Вызовите DbRegistry::set() при инициализации.'
            );
        }

        return self::$db;
    }

    /** Сбрасывает все соединения (например, в тестах). */
    public static function reset(): void
    {
        self::$db    = null;
        self::$named = [];
    }
}
