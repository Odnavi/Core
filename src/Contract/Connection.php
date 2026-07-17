<?php

namespace Odnavi\Core\Contract;

/**
 * Контракт соединения с БД, от которого зависит ORM. Реализуется адаптерами
 * (PDO, Doctrine DBAL, wpdb) — сам пакет не привязан к конкретному драйверу.
 *
 * Плейсхолдеры в SQL — позиционные `?` (как генерирует QueryBuilder). Адаптеры,
 * чей драйвер использует иной синтаксис (например, wpdb с %s/%d/%f), обязаны
 * выполнить преобразование внутри prepare().
 */
interface Connection
{
    /**
     * Готовит запрос с позиционными аргументами. Возвращает $this для цепочки
     * с методами fetch/execute.
     *
     * @param array<int, mixed> $args Аргументы для плейсхолдеров `?`.
     */
    public function prepare(string $sql, array $args): static;

    /** Возвращает одну строку результата (ассоциативный массив) или false. */
    public function fetch(): array|false;

    /**
     * Возвращает все строки результата.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array;

    /** Возвращает первое значение первой строки (для агрегатов/скаляров). */
    public function fetchOne(): int|string|false;

    /**
     * Возвращает первый столбец всех строк.
     *
     * @return array<int, mixed>
     */
    public function fetchFirstColumn(): array;

    /** Выполняет подготовленный не-SELECT запрос, возвращает число затронутых строк или false. */
    public function execute(): int|false;

    /**
     * Вставляет строку в таблицу.
     *
     * @param array<string, mixed> $data Колонка => значение.
     */
    public function insert(string $table, array $data): int|false;

    /**
     * Обновляет строки таблицы по критериям.
     *
     * @param array<string, mixed> $data  Колонка => новое значение.
     * @param array<string, mixed> $where Колонка => значение условия.
     */
    public function update(string $table, array $data, array $where): int|false;

    /**
     * Удаляет строки таблицы по критериям.
     *
     * @param array<string, mixed> $where Колонка => значение условия.
     */
    public function delete(string $table, array $where): int|false;

    /** Идентификатор последней вставленной строки или null. */
    public function lastInsertId(): int|string|null;

    /** Текст последней ошибки драйвера (пустая строка, если ошибок не было). */
    public function lastError(): string;

    /** Открывает транзакцию. */
    public function beginTransaction(): void;

    /** Фиксирует транзакцию. */
    public function commit(): void;

    /** Откатывает транзакцию. */
    public function rollBack(): void;

    /**
     * Выполняет callback в транзакции: коммит при успехе, откат и проброс при исключении.
     *
     * @throws \Throwable
     */
    public function transactional(callable $callback): mixed;
}
