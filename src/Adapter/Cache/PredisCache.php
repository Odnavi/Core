<?php

namespace Odnavi\Core\Adapter\Cache;

use Odnavi\Core\Contract\Cache;
use Predis\ClientInterface;

/**
 * Адаптер кэша поверх Predis (клиент Redis на чистом PHP). Помимо контракта
 * Cache (get/set/delete с сериализацией значений) предоставляет прямые
 * Redis-операции: очереди (push/pop), sorted set (zAdd/zScore/...), SET NX.
 */
class PredisCache implements Cache
{
    public function __construct(protected readonly ClientInterface $client) {}

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

    /**
     * Удаляет ключ (алиас delete для совместимости).
     */
    public function clear(string $key): void
    {
        $this->client->del($key);
    }

    /**
     * Атомарно записывает значение, только если ключа ещё нет (SET NX EX).
     *
     * @return bool true — записано; false — ключ уже существовал.
     */
    public function setNx(string $key, mixed $value, int $ttl): bool
    {
        return (bool) $this->client->set($key, $value, 'NX', 'EX', $ttl);
    }

    /**
     * Добавляет элемент в очередь (RPUSH).
     */
    public function push(string $queue, array $value): void
    {
        $this->client->rpush($queue, $value);
    }

    /**
     * Блокирующе извлекает элемент из очереди (BLPOP), десериализуя значение.
     *
     * @return array|null Значение или null по таймауту.
     */
    public function pop(string $queue, int $timeout = 0): ?array
    {
        $result = $this->client->blpop($queue, $timeout);

        return $result ? unserialize($result[1]) : null;
    }

    /**
     * Добавляет элемент в sorted set (ZADD).
     */
    public function zAdd(string $key, int $score, string $value): void
    {
        $this->client->zadd($key, $score, $value);
    }

    /**
     * Возвращает score элемента sorted set (ZSCORE) или null.
     */
    public function zScore(string $key, $score): ?string
    {
        return $this->client->zScore($key, $score);
    }

    /**
     * Элементы sorted set в диапазоне score (ZRANGEBYSCORE).
     *
     * @return array
     */
    public function zRangeByScore(string $key, int $min, int $max): array
    {
        return $this->client->zrangebyscore($key, $min, $max);
    }

    /**
     * Все элементы sorted set со score (ZRANGE ... WITHSCORES).
     *
     * @return array
     */
    public function zRangeWithScores(string $key): array
    {
        return $this->client->zrange($key, 0, -1, ['withscores' => true]);
    }

    /**
     * Удаляет элемент из sorted set (ZREM).
     */
    public function zRem(string $key, string $value): void
    {
        $this->client->zrem($key, $value);
    }
}
