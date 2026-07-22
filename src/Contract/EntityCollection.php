<?php

namespace Odnavi\Core\Contract;

use Countable;
use IteratorAggregate;

/**
 * Контракт коллекции сущностей, от которого зависит роутинг. Реализуется
 * ORM-слоем — сам пакет не привязан к конкретной ORM.
 */
interface EntityCollection extends Countable, IteratorAggregate
{
    /**
     * Преобразует коллекцию в массив.
     *
     * @param ?callable $callback Функция сериализации элемента; по умолчанию — Entity::toArray().
     *
     * @return array<int, mixed>
     */
    public function toArray(?callable $callback = null): array;

    /**
     * Применяет функцию к каждому элементу коллекции.
     *
     * @return array<int, mixed>
     */
    public function map(callable $callback): array;

    /** Общее число записей без учёта limit/offset (для пагинации). */
    public function getTotal(): int;
}
