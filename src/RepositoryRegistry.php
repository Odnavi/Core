<?php

namespace Odnavi\Core;

use Odnavi\Core\Contract\Repository;
use RuntimeException;

/**
 * Держит резолвер репозиториев (entityClass => Repository), которым пользуется
 * роутинг для generic-операций (#[Get]/#[Post]/...). В отличие от DbRegistry/
 * CacheRegistry, репозиторий нужен per-класс сущности, поэтому регистрируется
 * не готовый инстанс, а фабричная функция. Приложение задаёт её один раз при
 * инициализации.
 */
final class RepositoryRegistry
{
    /** @var (callable(string): Repository)|null */
    private static $resolver = null;

    /**
     * Регистрирует резолвер репозиториев.
     *
     * @param callable(string): Repository $resolver
     */
    public static function setResolver(callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Возвращает репозиторий для класса сущности через зарегистрированный резолвер.
     *
     * @throws RuntimeException Если резолвер не зарегистрирован.
     */
    public static function get(string $entityClass): Repository
    {
        if (self::$resolver === null) {
            throw new RuntimeException(
                'Роутинг: резолвер репозиториев не зарегистрирован. Вызовите '
                . 'RepositoryRegistry::setResolver() при инициализации, если '
                . 'контроллер использует #[Entity]-операции.'
            );
        }

        return (self::$resolver)($entityClass);
    }

    /** Сбрасывает резолвер (например, в тестах). */
    public static function reset(): void
    {
        self::$resolver = null;
    }
}
