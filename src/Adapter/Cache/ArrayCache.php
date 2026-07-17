<?php

namespace Odnavi\Core\Adapter\Cache;

use Odnavi\Core\Contract\Cache;

/**
 * Кэш в памяти процесса. Живёт в пределах одного запроса/воркер-цикла и не
 * требует внешнего хранилища — удобно для тестов и локальной разработки.
 */
final class ArrayCache implements Cache
{
    /** @var array<string, array{value: mixed, expires: ?int}> */
    private array $store = [];

    public function get(string $key): mixed
    {
        $item = $this->store[$key] ?? null;

        if ($item === null) {
            return null;
        }

        if ($item['expires'] !== null && $item['expires'] <= time()) {
            unset($this->store[$key]);
            return null;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->store[$key] = [
            'value'   => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }
}
