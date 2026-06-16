<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Consumer;

use Idaratech\EventBus\EventSerializer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ReplayDlqCommand extends Command
{
    protected $signature = 'eventbus:replay-dlq
        {queue : The DLQ queue name to read from}
        {exchange : The target exchange to republish to}
        {--limit=100 : Maximum number of messages to replay}';

    protected $description = 'Replay messages from a dead-letter queue';

    public function handle(): int
    {
        $queueName = $this->argument('queue');
        $exchange = $this->argument('exchange');
        $limit = (int) $this->option('limit');

        $connectionConfig = config('eventbus.connection');
        $connection = new AMQPLazyConnection(
            host: $connectionConfig['host'],
            port: (int) $connectionConfig['port'],
            user: $connectionConfig['user'],
            password: $connectionConfig['password'],
            vhost: $connectionConfig['vhost'] ?? '/',
            heartbeat: (int) ($connectionConfig['heartbeat'] ?? 30),
        );

        $channel = $connection->channel();
        $serializer = new EventSerializer();

        $replayed = 0;
        $failed = 0;

        $this->info("Replaying messages from DLQ '{$queueName}' to exchange '{$exchange}'...");

        try {
            while ($replayed < $limit) {
                $message = $channel->basic_get($queueName);

                if ($message === null) {
                    $this->info('No more messages in DLQ.');
                    break;
                }

                try {
                    $event = $serializer->deserialize($message->getBody());
                    $resetEvent = $event->withRetryCount(0);

                    $replayMessage = new AMQPMessage(
                        $resetEvent->toJson(),
                        [
                            'content_type' => 'application/json',
                            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                            'message_id' => $resetEvent->getEventId(),
                            'app_id' => $resetEvent->getSource(),
                        ],
                    );

                    $channel->basic_publish(
                        $replayMessage,
                        $exchange,
                        $event->getEventType(),
                    );

                    $channel->basic_ack($message->getDeliveryTag());
                    $replayed++;

                    Log::info('EventBus DLQ replay: republished message', [
                        'event_id' => $event->getEventId(),
                        'event_type' => $event->getEventType(),
                        'exchange' => $exchange,
                    ]);
                } catch (\Throwable $e) {
                    $failed++;

                    Log::error('EventBus DLQ replay: failed to replay message', [
                        'error' => $e->getMessage(),
                        'body' => mb_substr($message->getBody(), 0, 500),
                    ]);

                    $channel->basic_reject($message->getDeliveryTag(), false);
                }
            }
        } finally {
            try {
                $channel->close();
                $connection->close();
            } catch (\Throwable) {
                // Ignore close errors
            }
        }

        $this->info("Replay complete. Replayed: {$replayed}, Failed: {$failed}");

        return self::SUCCESS;
    }
}
