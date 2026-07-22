<?php

namespace Odnavi\Core\Contract;

/**
 * Контракт репозитория сущности, от которого зависит роутинг (generic-операции
 * #[Get]/#[Post]/...). Реализуется ORM-слоем — сам пакет не привязан
 * к конкретной ORM. Резолвится по имени класса сущности через RepositoryRegistry.
 */
interface Repository
{
    /** Находит сущность по идентификатору. */
    public function find(int|string $id): Entity;

    /**
     * Находит сущности по критериям.
     *
     * @param array<string, mixed> $criteria
     * @param ?array<string, string> $orderBy
     */
    public function findAll(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        bool $withTotal = false
    ): EntityCollection;

    public function create(Entity $entity): bool;

    public function update(Entity $entity): bool;

    public function delete(Entity $entity): bool;

    /** Класс сущности, с которым работает репозиторий. */
    public function getEntityClass(): string;
}
