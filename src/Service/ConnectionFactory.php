<?php

namespace Odnavi\Core\Service;

use Odnavi\Core\Connection\{DbalConnection};
use Odnavi\Core\Connection\PdoConnection;
use Odnavi\Core\Connection\WpdbConnection;
use Odnavi\Core\Contract\Connection;
use InvalidArgumentException;
use PDO;

/**
 * Оборачивает объект работы с БД проекта в подходящий адаптер Connection.
 * Тип драйвера (PDO, Doctrine DBAL, wpdb) определяется автоматически, поэтому
 * приложению не нужно самому выбирать адаптер.
 */
final class ConnectionFactory
{
    /** FQCN драйвера Doctrine DBAL — по строке, чтобы не требовать пакет на автозагрузке. */
    private const DBAL_DRIVER = 'Doctrine\\DBAL\\Connection';

    /**
     * Создаёт адаптер соединения из драйвера БД проекта.
     *
     * @param object $db Драйвер: PDO, Doctrine\DBAL\Connection, wpdb или готовый Connection.
     *
     * @return Connection
     * @throws InvalidArgumentException Если тип драйвера не поддерживается.
     */
    public static function from(object $db): Connection
    {
        return match (true) {
            $db instanceof Connection => $db,
            $db instanceof PDO        => new PdoConnection($db),
            self::isDbal($db)         => new DbalConnection($db),
            self::isWpdb($db)         => new WpdbConnection($db),
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
