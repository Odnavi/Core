<?php

namespace Odnavi\Core\Connection;

use Odnavi\Core\Contract\Connection;
use Doctrine\DBAL\Connection as DbalDriver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Statement;
use Throwable;

/**
 * Адаптер соединения поверх Doctrine DBAL. Позиционные плейсхолдеры `?`,
 * 1-based bindValue. Требует doctrine/dbal.
 */
final class DbalConnection implements Connection
{
    private Statement $query;
    private string    $lastError = '';

    public function __construct(private readonly DbalDriver $connection) {}

    public function prepare(string $sql, array $args): static
    {
        $this->query = $this->connection->prepare($sql);

        foreach (array_values($args) as $index => $value) {
            $this->query->bindValue($index + 1, $value);
        }

        return $this;
    }

    public function fetch(): array|false
    {
        return $this->query->executeQuery()->fetchAssociative();
    }

    public function fetchAll(): array
    {
        return $this->query->executeQuery()->fetchAllAssociative();
    }

    public function fetchOne(): int|string|false
    {
        return $this->query->executeQuery()->fetchOne();
    }

    public function fetchFirstColumn(): array
    {
        return $this->query->executeQuery()->fetchFirstColumn();
    }

    public function execute(): int|false
    {
        try {
            return $this->query->executeStatement();
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function insert(string $table, array $data): int|false
    {
        try {
            return $this->connection->insert($table, $data);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function update(string $table, array $data, array $where): int|false
    {
        try {
            return $this->connection->update($table, $data, $where);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function delete(string $table, array $where): int|false
    {
        try {
            return $this->connection->delete($table, $where);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function lastInsertId(): int|string|null
    {
        try {
            return $this->connection->lastInsertId() ?? null;
        } catch (Exception) {
            return null;
        }
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
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
}
