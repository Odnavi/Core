<?php

namespace Odnavi\Core\Adapter\Cache;

use Odnavi\Core\Contract\Cache;
use Redis;

/**
 * Адаптер кэша поверх расширения ext-redis (класс \Redis). Значения
 * сериализуются вручную, чтобы поведение совпадало с PredisCache и не зависело
 * от настроенного в расширении сериализатора.
 */
final class PhpRedisCache implements Cache
{
    public function __construct(private readonly Redis $redis) {}

    public function get(string $key): mixed
    {
        $raw = $this->redis->get($key);

        return $raw === false ? null : unserialize($raw);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $payload = serialize($value);

        if ($ttl !== null) {
            $this->redis->setex($key, $ttl, $payload);
            return;
        }

        $this->redis->set($key, $payload);
    }

    public function delete(string $key): void
    {
        $this->redis->del($key);
    }
}
