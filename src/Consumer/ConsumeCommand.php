<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Consumer;

use Idaratech\EventBus\Contracts\EventHandlerInterface;
use Idaratech\EventBus\EventSerializer;
use Idaratech\EventBus\Middleware\LogContextMiddleware;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumeCommand extends Command
{
    protected $signature = 'eventbus:consume
        {--memory-limit=128 : Memory limit in MB}
        {--timeout=0 : Timeout in seconds (0 = unlimited)}';

    protected $description = 'Consume messages from the event bus';

    private bool $shouldStop = false;

    private EventSerializer $serializer;

    public function handle(): int
    {
        $this->serializer = new EventSerializer();
        $this->registerSignalHandlers();

        $consumers = config('eventbus.consumers', []);

        if (empty($consumers)) {
            $this->error('No consumers configured. Add entries to config("eventbus.consumers").');

            return self::FAILURE;
        }

        $memoryLimit = (int) $this->option('memory-limit') * 1024 * 1024;
        $timeout = (int) $this->option('timeout');
        $startTime = time();
        $backoff = 1;
        $maxBackoff = 30;

        while (!$this->shouldStop) {
            $connection = null;
            $channel = null;

            try {
                $connectionConfig = config('eventbus.connection');
                $connection = new AMQPLazyConnection(
                    host: $connectionConfig['host'],
                    port: (int) $connectionConfig['port'],
                    user: $connectionConfig['user'],
                    password: $connectionConfig['password'],
                    vhost: $connectionConfig['vhost'] ?? '/',
                    heartbeat: (int) ($connectionConfig['heartbeat'] ?? 30),
                    connection_timeout: (float) ($connectionConfig['connection_timeout'] ?? 10.0),
                    read_write_timeout: (float) ($connectionConfig['read_write_timeout'] ?? 60.0),
                );

                $channel = $connection->channel();

                foreach ($consumers as $consumerArray) {
                    $consumerConfig = new ConsumerConfig(...$consumerArray);
                    $this->setupConsumer($channel, $consumerConfig);
                }

                $this->info('EventBus consumer started. Waiting for messages...');
                $backoff = 1;

                while (!$this->shouldStop && $channel->is_consuming()) {
                    try {
                        $channel->wait(null, true, 1);
                    } catch (\Throwable) {
                        // Timeout on wait is expected
                    }

                    gc_collect_cycles();

                    if (memory_get_usage(true) > $memoryLimit) {
                        $this->warn('Memory limit reached. Stopping consumer.');
                        $this->shouldStop = true;
                        break;
                    }

                    if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                        $this->info('Timeout reached. Stopping consumer.');
                        $this->shouldStop = true;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                if (!$this->shouldStop) {
                    $jitter = rand(0, (int) ($backoff * 500));
                    $wait = $backoff * 1000 + $jitter;
                    Log::warning('EventBus consumer: connection lost, reconnecting', [
                        'error' => $e->getMessage(),
                        'retry_in_ms' => $wait,
                    ]);
                    $this->warn("Connection lost: {$e->getMessage()}. Retrying in {$wait}ms...");
                    usleep($wait * 1000);
                    $backoff = min($backoff * 2, $maxBackoff);
                }
            } finally {
                try {
                    $channel?->close();
                    $connection?->close();
                } catch (\Throwable) {
                    // Ignore close errors during shutdown
                }
            }
        }

        $this->info('EventBus consumer stopped.');

        return self::SUCCESS;
    }

    private function setupConsumer(AMQPChannel $channel, ConsumerConfig $config): void
    {
        $channel->basic_qos(0, $config->prefetchCount, false);

        $channel->basic_consume(
            queue: $config->queue,
            consumer_tag: '',
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: function (AMQPMessage $message) use ($channel, $config) {
                $this->processMessage($message, $channel, $config);
            },
        );
    }

    private function processMessage(AMQPMessage $message, AMQPChannel $channel, ConsumerConfig $config): void
    {
        $beforeHook = config('eventbus.hooks.before_handle');
        $afterHook = config('eventbus.hooks.after_handle');
        $event = null;

        try {
            $event = $this->serializer->deserialize($message->getBody());

            LogContextMiddleware::before($event);

            if ($beforeHook !== null && is_callable($beforeHook)) {
                call_user_func($beforeHook, $event);
            }

            /** @var EventHandlerInterface[] $handlers */
            $handlers = app()->tagged('eventbus.handlers');

            foreach ($handlers as $handler) {
                if ($handler->supports($event->getEventType())) {
                    $handler->handle($event);
                }
            }

            $channel->basic_ack($message->getDeliveryTag());

            Log::debug('EventBus: message processed successfully', [
                'event_id' => $event->getEventId(),
                'event_type' => $event->getEventType(),
            ]);
        } catch (InvalidArgumentException $e) {
            Log::error('EventBus: invalid message, sending to DLQ', [
                'error' => $e->getMessage(),
                'body' => mb_substr($message->getBody(), 0, 500),
            ]);

            $channel->basic_reject($message->getDeliveryTag(), false);
        } catch (\Throwable $e) {
            $this->handleFailure($message, $channel, $config, $e, $event);
        } finally {
            if ($afterHook !== null && is_callable($afterHook)) {
                call_user_func($afterHook, $event);
            }

            LogContextMiddleware::after();
        }
    }

    private function handleFailure(
        AMQPMessage $message,
        AMQPChannel $channel,
        ConsumerConfig $config,
        \Throwable $exception,
        ?\Idaratech\EventBus\Contracts\IntegrationEventInterface $event = null,
    ): void {
        Log::error('EventBus: message processing failed', [
            'error' => $exception->getMessage(),
            'body' => mb_substr($message->getBody(), 0, 500),
        ]);

        try {
            $event ??= $this->serializer->deserialize($message->getBody());
            $retryCount = $event->getMetadata()['retry_count'] ?? 0;

            if ($retryCount < $config->maxRetries) {
                $delay = $config->retryDelays[$retryCount] ?? end($config->retryDelays);
                $retriedEvent = $event->withRetryCount($retryCount + 1);

                $retryMessage = new AMQPMessage(
                    $retriedEvent->toJson(),
                    [
                        'content_type' => 'application/json',
                        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                        'message_id' => $retriedEvent->getEventId(),
                        'app_id' => $retriedEvent->getSource(),
                        'expiration' => (string) $delay,
                    ],
                );

                $channel->basic_publish(
                    $retryMessage,
                    $config->retryExchange,
                    $event->getEventType(),
                );

                Log::info('EventBus: message queued for retry', [
                    'event_id' => $event->getEventId(),
                    'retry_count' => $retryCount + 1,
                    'delay_ms' => $delay,
                ]);
            } else {
                Log::warning('EventBus: max retries exceeded, sending to DLQ', [
                    'event_id' => $event->getEventId(),
                    'retry_count' => $retryCount,
                ]);
            }
        } catch (\Throwable $innerException) {
            Log::error('EventBus: failed to handle retry logic', [
                'error' => $innerException->getMessage(),
            ]);
        }

        $channel->basic_reject($message->getDeliveryTag(), false);
    }

    private function registerSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->info('Received SIGTERM. Shutting down gracefully...');
            $this->shouldStop = true;
        });

        pcntl_signal(SIGINT, function () {
            $this->info('Received SIGINT. Shutting down gracefully...');
            $this->shouldStop = true;
        });
    }
}
