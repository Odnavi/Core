<?php

namespace Odnavi\Core\Contract;

/**
 * Контракт доменной сущности, от которого зависит роутинг (generic-операции
 * #[Get]/#[Post]/...). Реализуется ORM-слоем — сам пакет не привязан
 * к конкретной ORM.
 */
interface Entity
{
    /**
     * Преобразует сущность в массив.
     *
     * @param ?array $fields Если задан и не ['all'] — поля, которые нужно исключить.
     *
     * @return array<string, mixed>
     */
    public function toArray(?array $fields = null): array;

    /**
     * Заполняет сущность данными из массива.
     *
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): static;

    /** Догружает связанные сущности (если они есть). */
    public function preloadRelations(): void;
}
