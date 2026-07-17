<?php

namespace Odnavi\Core\Adapter\Cache;

use Odnavi\Core\Contract\Cache;
use Predis\ClientInterface;

/**
 * Адаптер кэша поверх Predis (клиент Redis на чистом PHP). Значения
 * сериализуются, поэтому в кэш можно класть массивы и объекты, а не только строки.
 */
final class PredisCache implements Cache
{
    public function __construct(private readonly ClientInterface $client) {}

    public function get(string $key): mixed
    {
        $raw = $this->client->get($key);

        return $raw === null ? null : unserialize($raw);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $payload = serialize($value);

        if ($ttl !== null) {
            $this->client->setex($key, $ttl, $payload);
            return;
        }

        $this->client->set($key, $payload);
    }

    public function delete(string $key): void
    {
        $this->client->del($key);
    }
}
