<?php

namespace Odnavi\Core\Adapter\Queue;

use Odnavi\Core\Contract\Queue;
use LogicException;

/**
 * Очередь-заглушка: выполняет задачу немедленно, в том же процессе. Нужна там,
 * где брокера нет — тесты, CLI-скрипты, локальный запуск без RabbitMQ. Поведение
 * совпадает с прежним синхронным вызовом, поэтому включение очереди не может
 * сломать код, который к ней не готов.
 */
final class SyncQueue implements Queue
{
    /** @var callable|null Обработчик задач; без него публикация невозможна. */
    private $handler;

    /** @var callable|null Текущее приложение процесса: fn(): string. */
    private $currentApp;

    /**
     * @param ?callable $handler    fn(string $job, array $payload): void
     * @param ?callable $currentApp fn(): string — приложение процесса; нужно,
     *                              чтобы отличить свою задачу от чужой.
     */
    public function __construct(?callable $handler = null, ?callable $currentApp = null)
    {
        $this->handler    = $handler;
        $this->currentApp = $currentApp;
    }

    public function publish(string $job, array $payload = [], ?string $app = null): void
    {
        // Синхронная очередь выполняет задачу в текущем процессе, а он поднят
        // под одно приложение — чужую задачу выполнить нечем.
        if ($app !== null && $app !== ($this->currentApp ? ($this->currentApp)() : '')) {
            throw new LogicException("SyncQueue: задачу '$job' нельзя выполнить для чужого приложения '$app'");
        }

        if ($this->handler === null) {
            throw new LogicException("SyncQueue: обработчик не задан, задачу '$job' некому выполнить");
        }

        ($this->handler)($job, $payload);
    }

    public function consume(callable $handler, ?callable $shouldStop = null): void
    {
        throw new LogicException('SyncQueue: нечего слушать, задачи выполняются при публикации');
    }
}
