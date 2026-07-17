<?php

namespace Odnavi\Core\Contract;

/**
 * Контракт разделяемого кэша, от которого зависит ORM. По умолчанию используется
 * NullCache (no-op); приложение может внедрить свою реализацию (Redis, APCu и т.п.)
 * через Support\Caching::set() — сам пакет не привязан к конкретному хранилищу.
 *
 * Значения кэшируются как есть; сериализацию сложных типов, если она нужна,
 * выполняет реализация адаптера.
 */
interface Cache
{
    /**
     * Возвращает значение по ключу или null, если ключа нет.
     *
     * @param string $key Ключ.
     *
     * @return mixed Сохранённое значение либо null.
     */
    public function get(string $key): mixed;

    /**
     * Сохраняет значение по ключу.
     *
     * @param string $key   Ключ.
     * @param mixed  $value Значение.
     * @param ?int   $ttl   Время жизни в секундах (null — без ограничения).
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Удаляет значение по ключу.
     *
     * @param string $key Ключ.
     */
    public function delete(string $key): void;
}
