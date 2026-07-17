<?php

namespace Odnavi\Core\Service;

use Odnavi\Core\Adapter\Cache\PhpRedisCache;
use Odnavi\Core\Adapter\Cache\PredisCache;
use Odnavi\Core\Contract\Cache;
use InvalidArgumentException;
use Redis;

/**
 * Оборачивает клиент кэша проекта в подходящий адаптер Cache. Тип клиента
 * (Predis, ext-redis) определяется автоматически, поэтому приложению не нужно
 * самому выбирать адаптер.
 */
final class CacheFactory
{
    /** FQCN клиента Predis — по строке, чтобы не требовать пакет на автозагрузке. */
    private const PREDIS_CLIENT = 'Predis\\ClientInterface';

    /**
     * Создаёт адаптер кэша из клиента проекта.
     *
     * @param object $client Клиент: Predis\ClientInterface, \Redis или готовый Cache.
     *
     * @return Cache
     * @throws InvalidArgumentException Если тип клиента не поддерживается.
     */
    public static function from(object $client): Cache
    {
        return match (true) {
            $client instanceof Cache => $client,
            $client instanceof Redis => new PhpRedisCache($client),
            self::isPredis($client)  => new PredisCache($client),
            default => throw new InvalidArgumentException(
                'Cache: неподдерживаемый клиент кэша: ' . $client::class
                . '. Ожидается Predis\\ClientInterface или \\Redis.'
            ),
        };
    }

    /** Проверяет клиент Predis, только если пакет установлен. */
    private static function isPredis(object $client): bool
    {
        return interface_exists(self::PREDIS_CLIENT) && $client instanceof (self::PREDIS_CLIENT);
    }
}
