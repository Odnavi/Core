<?php

namespace Odnavi\Core\Connection;

use Odnavi\Core\Contract\Connection;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Адаптер соединения поверх сырого PDO. Позиционные плейсхолдеры `?`,
 * 1-based bindValue — совпадает с тем, что генерирует QueryBuilder.
 */
final class PdoConnection implements Connection
{
    private ?PDOStatement $statement = null;

    /** @var array<int, mixed> */
    private array $bindings = [];

    private string $lastError = '';

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function prepare(string $sql, array $args): static
    {
        $this->statement = $this->pdo->prepare($sql);
        $this->bindings  = array_values($args);
        return $this;
    }

    public function fetch(): array|false
    {
        return $this->run()->fetch();
    }

    public function fetchAll(): array
    {
        return $this->run()->fetchAll();
    }

    public function fetchOne(): int|string|false
    {
        return $this->run()->fetchColumn();
    }

    public function fetchFirstColumn(): array
    {
        return $this->run()->fetchAll(PDO::FETCH_COLUMN);
    }

    public function execute(): int|false
    {
        try {
            return $this->run()->rowCount();
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function insert(string $table, array $data): int|false
    {
        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql          = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), $placeholders);

        return $this->change($sql, array_values($data));
    }

    public function update(string $table, array $data, array $where): int|false
    {
        if (!$data) {
            return 0;
        }

        $set  = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($data)));
        $sql  = sprintf('UPDATE %s SET %s', $table, $set);
        $args = array_values($data);

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', array_map(static fn($c) => "$c = ?", array_keys($where)));
            $args = array_merge($args, array_values($where));
        }

        return $this->change($sql, $args);
    }

    public function delete(string $table, array $where): int|false
    {
        $sql  = 'DELETE FROM ' . $table;
        $args = [];

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', array_map(static fn($c) => "$c = ?", array_keys($where)));
            $args = array_values($where);
        }

        return $this->change($sql, $args);
    }

    public function lastInsertId(): int|string|null
    {
        $id = $this->pdo->lastInsertId();
        return $id === false ? null : $id;
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
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

    /** Выполняет текущий подготовленный запрос с привязкой аргументов. */
    private function run(): PDOStatement
    {
        foreach ($this->bindings as $index => $value) {
            $this->statement->bindValue($index + 1, $value, $this->paramType($value));
        }

        $this->statement->execute();
        return $this->statement;
    }

    /** Готовит и выполняет изменяющий запрос, возвращая число затронутых строк. */
    private function change(string $sql, array $args): int|false
    {
        try {
            return $this->prepare($sql, $args)->run()->rowCount();
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    private function paramType(mixed $value): int
    {
        return match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
    }
}
