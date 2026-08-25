<?php

namespace Odnavi\Core;

use Odnavi\Core\Adapter\Queue\SyncQueue;
use Odnavi\Core\Contract\Queue;

/**
 * Держит активную очередь. Приложение регистрирует реализацию один раз при
 * инициализации; допускается ленивая регистрация через фабрику — тогда объект
 * создаётся при первом обращении.
 *
 * Пока очередь не задана, используется SyncQueue без обработчика: попытка
 * опубликовать задачу упадёт с внятной ошибкой. Молча терять задачи хуже,
 * чем упасть, поэтому no-op здесь не подходит.
 */
final class QueueRegistry
{
    private static ?Queue $queue = null;

    /** @var callable|null Фабрика очереди для ленивой инициализации. */
    private static $factory = null;

    /**
     * Регистрирует активную очередь.
     *
     * @param Queue|callable $queue Готовая реализация либо фабрика fn(): Queue.
     */
    public static function set(Queue|callable $queue): void
    {
        if ($queue instanceof Queue) {
            self::$queue   = $queue;
            self::$factory = null;
            return;
        }

        self::$queue   = null;
        self::$factory = $queue;
    }

    /** Возвращает активную очередь (SyncQueue без обработчика, если не задана). */
    public static function get(): Queue
    {
        if (self::$queue !== null) {
            return self::$queue;
        }

        return self::$queue = self::$factory
            ? (self::$factory)()
            : new SyncQueue();
    }

    /** Сбрасывает очередь (например, в тестах). */
    public static function reset(): void
    {
        self::$queue   = null;
        self::$factory = null;
    }
}
