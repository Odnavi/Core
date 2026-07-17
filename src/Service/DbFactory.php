<?php

namespace Odnavi\Core\Service;

use Odnavi\Core\Adapter\Db\{DbalDb};
use Odnavi\Core\Adapter\Db\PdoDb;
use Odnavi\Core\Adapter\Db\WpdbDb;
use Odnavi\Core\Contract\Db;
use InvalidArgumentException;
use PDO;

/**
 * Оборачивает объект работы с БД проекта в подходящий адаптер Db.
 * Тип драйвера (PDO, Doctrine DBAL, wpdb) определяется автоматически, поэтому
 * приложению не нужно самому выбирать адаптер.
 */
final class DbFactory
{
    /** FQCN драйвера Doctrine DBAL — по строке, чтобы не требовать пакет на автозагрузке. */
    private const DBAL_DRIVER = 'Doctrine\\DBAL\\Connection';

    /**
     * Создаёт адаптер соединения из драйвера БД проекта.
     *
     * @param object $db Драйвер: PDO, Doctrine\DBAL\Connection, wpdb или готовый Db.
     *
     * @return Db
     * @throws InvalidArgumentException Если тип драйвера не поддерживается.
     */
    public static function from(object $db): Db
    {
        return match (true) {
            $db instanceof Db  => $db,
            $db instanceof PDO => new PdoDb($db),
            self::isDbal($db)  => new DbalDb($db),
            self::isWpdb($db)  => new WpdbDb($db),
            default => throw new InvalidArgumentException(
                'ORM: неподдерживаемый драйвер БД: ' . $db::class
                . '. Ожидается PDO, Doctrine\\DBAL\\Connection или wpdb.'
            ),
        };
    }

    /** Проверяет драйвер Doctrine DBAL, только если пакет установлен. */
    private static function isDbal(object $db): bool
    {
        return class_exists(self::DBAL_DRIVER) && $db instanceof (self::DBAL_DRIVER);
    }

    /** Определяет wpdb по классу и API, чтобы не зависеть от загрузки WordPress. */
    private static function isWpdb(object $db): bool
    {
        return str_ends_with($db::class, 'wpdb') && method_exists($db, 'get_results');
    }
}
