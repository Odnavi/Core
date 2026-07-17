<?php

namespace Odnavi\Core\Contract;

/**
 * Минимальный контракт профайлера для ORM. По умолчанию используется NullProfiler
 * (no-op); приложение может внедрить свою реализацию через Support\Profiling::set().
 */
interface Profiler
{
    /**
     * Начинает замер с меткой.
     *
     * @param array<string, mixed> $context Доп. данные (sql, args и т.п.).
     */
    public function start(string $label, array $context = []): void;

    /** Завершает последний открытый замер. */
    public function stop(): void;
}
