<?php

namespace Odnavi\Core\Adapter\Queue;

use Odnavi\Core\Contract\Queue;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * Очередь поверх RabbitMQ. Соединение ленивое: HTTP-запрос, который ничего
 * не публикует, не платит за коннект к брокеру.
 *
 * Очередь одна на все приложения, имя берётся из конфига. Приложение-адресат
 * едет в метаданных сообщения — маршрутизацией занимается не брокер, а сам
 * консьюмер, переключая под него контекст.
 *
 * Топология: основная очередь с dead-letter-exchange. Задача, обработчик
 * которой бросил исключение, уходит в очередь неудачных (не теряется
 * и не крутится в бесконечном цикле повторов).
 */
final class AmqpQueue implements Queue
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    /**
     * Очереди, топология которых уже объявлена в этом соединении.
     *
     * @var array<string, true>
     */
    private array $declared = [];

    /**
     * @param array{host: string, port: int, user: string, password: string,
     *              vhost: string, queue: string, app?: callable(): string} $config
     *        Конфиг подключения и топологии. `app` — текущее приложение
     *        публикатора; замыканием, потому что у консьюмера оно меняется.
     */
    public function __construct(private readonly array $config) {}

    public function publish(string $job, array $payload = [], ?string $app = null): void
    {
        $queue = $this->config['queue'];

        // Объявляем очередь: publish в несуществующую очередь через default
        // exchange молча теряет сообщение, ошибки не будет.
        $this->declare($queue);

        $message = new AMQPMessage(
            json_encode([
                'job'     => $job,
                'payload' => $payload,
                // app здесь — управляющее поле: очередь общая, и по нему
                // консьюмер выбирает базу, кэш и белый список приложения.
                // published_at даёт время ожидания в очереди.
                'meta'    => [
                    'app'          => $app ?? $this->currentApp(),
                    'published_at' => time(),
                ],
            ], JSON_UNESCAPED_UNICODE),
            [
                'content_type'  => 'application/json',
                // Без этого сообщение живёт только в памяти брокера и пропадёт при рестарте.
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]
        );

        $this->channel()->basic_publish($message, '', $queue);
    }

    public function consume(callable $handler, ?callable $shouldStop = null): void
    {
        $channel = $this->channel();
        $queue   = $this->config['queue'];

        $this->declare($queue);

        // Без prefetch брокер отдаёт воркеру всё, до чего дотянется: один
        // медленный consumer заберёт очередь себе, остальные будут простаивать.
        $channel->basic_qos(0, 1, false);

        $stopped = false;

        $tag = $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            static function (AMQPMessage $message) use ($handler, $shouldStop, &$stopped): void {
                $body = json_decode($message->getBody(), true);

                if (!is_array($body) || !isset($body['job'])) {
                    // Разобрать не смогли — повтор не поможет, отправляем в DLQ.
                    $message->nack(false);
                    return;
                }

                try {
                    $handler($body['job'], $body['payload'] ?? [], $body['meta'] ?? []);
                    $message->ack();
                } catch (Throwable) {
                    $message->nack(false);
                }

                // Проверяем только после ack/nack: выход раньше означал бы
                // повторную доставку уже выполненной задачи.
                $stopped = $shouldStop && $shouldStop();
            }
        );

        while ($channel->is_consuming()) {
            $channel->wait();

            if ($stopped) {
                $channel->basic_cancel($tag);
                break;
            }
        }
    }

    /** Приложение процесса-публикатора; пустая строка — вне контекста приложения. */
    private function currentApp(): string
    {
        return isset($this->config['app']) ? ($this->config['app'])() : '';
    }

    /** Закрывает канал и соединение, если они были открыты. */
    public function close(): void
    {
        $this->channel?->close();
        $this->connection?->close();

        $this->channel    = null;
        $this->connection = null;
        $this->declared   = [];
    }

    /**
     * Объявляет очередь вместе с её dead-letter-парой. Объявление идемпотентно,
     * но не бесплатно — каждое стоит round-trip к брокеру, поэтому кэшируем.
     *
     * @param string $queue Имя очереди.
     */
    private function declare(string $queue): void
    {
        if (isset($this->declared[$queue])) {
            return;
        }

        $channel  = $this->channel();
        $exchange = "$queue.dlx";
        $failed   = "$queue.failed";

        $channel->exchange_declare($exchange, 'fanout', false, true, false);
        $channel->queue_declare($failed, false, true, false, false);
        $channel->queue_bind($failed, $exchange);

        $channel->queue_declare($queue, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => $exchange,
        ]));

        $this->declared[$queue] = true;
    }

    /** Возвращает канал, при первом обращении открывая соединение. */
    private function channel(): AMQPChannel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        $this->connection = new AMQPStreamConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['user'],
            $this->config['password'],
            $this->config['vhost'],
        );

        return $this->channel = $this->connection->channel();
    }
}
