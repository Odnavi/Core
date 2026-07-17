<?php

namespace Odnavi\Core\Connection;

use Odnavi\Core\Contract\Connection;
use Throwable;
use const ARRAY_A;

/**
 * Адаптер соединения поверх WordPress $wpdb.
 *
 * Особенность: wpdb не понимает позиционные `?` — он использует %s/%d/%f. Поэтому
 * prepare() конвертирует `?` в формат wpdb по типу аргумента и прогоняет строку
 * через $wpdb->prepare(). insert/update/delete делегируются нативным методам wpdb.
 *
 * Внимание: класс исполняется только в среде WordPress (тип $wpdb намеренно object,
 * чтобы пакет не требовал WP на этапе автозагрузки). Под тестами не покрыт.
 */
final class WpdbConnection implements Connection
{
    /** Подготовленный (через $wpdb->prepare) SQL для текущего запроса. */
    private string $prepared = '';

    /** @param object $wpdb Экземпляр \wpdb. */
    public function __construct(private readonly object $wpdb) {}

    public function prepare(string $sql, array $args): static
    {
        $args = array_values($args);

        if (!$args) {
            $this->prepared = $sql;
            return $this;
        }

        $index     = 0;
        $converted = preg_replace_callback('/\?/', static function () use (&$index, $args) {
            $value = $args[$index] ?? null;
            $index++;

            return match (true) {
                is_int($value)   => '%d',
                is_float($value) => '%f',
                default          => '%s',
            };
        }, $sql);

        $this->prepared = $this->wpdb->prepare($converted, $args);
        return $this;
    }

    public function fetch(): array|false
    {
        $row = $this->wpdb->get_row($this->prepared, $this->arrayA());
        return is_array($row) ? $row : false;
    }

    public function fetchAll(): array
    {
        return $this->wpdb->get_results($this->prepared, $this->arrayA()) ?: [];
    }

    public function fetchOne(): int|string|false
    {
        $value = $this->wpdb->get_var($this->prepared);
        return $value === null ? false : $value;
    }

    public function fetchFirstColumn(): array
    {
        return $this->wpdb->get_col($this->prepared) ?: [];
    }

    public function execute(): int|false
    {
        $result = $this->wpdb->query($this->prepared);
        return $result === false ? false : (int) $result;
    }

    public function insert(string $table, array $data): int|false
    {
        return $this->wpdb->insert($table, $data);
    }

    public function update(string $table, array $data, array $where): int|false
    {
        return $this->wpdb->update($table, $data, $where);
    }

    public function delete(string $table, array $where): int|false
    {
        return $this->wpdb->delete($table, $where);
    }

    public function lastInsertId(): int|string|null
    {
        return $this->wpdb->insert_id ?: null;
    }

    public function lastError(): string
    {
        return (string) $this->wpdb->last_error;
    }

    public function beginTransaction(): void
    {
        $this->wpdb->query('START TRANSACTION');
    }

    public function commit(): void
    {
        $this->wpdb->query('COMMIT');
    }

    public function rollBack(): void
    {
        $this->wpdb->query('ROLLBACK');
    }

    public function transactional(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /** Константа ARRAY_A из WordPress (ассоциативный вывод). */
    private function arrayA(): string
    {
        return defined('ARRAY_A') ? ARRAY_A : 'ARRAY_A';
    }
}
