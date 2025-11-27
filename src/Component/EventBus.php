<?php
namespace App\Component;

use PhpAmqpLib\Message\AMQPMessage;

class EventBus
{
    private $channel;
    private $exchangeName;

    public function __construct($channel, $exchangeName = 'event_bus_topic')
    {
        $this->channel = $channel;
        $this->exchangeName = $exchangeName;
        // 宣告 Topic Exchange
        $this->channel->exchange_declare($exchangeName, 'topic', false, true, false);
    }

    /**
     * 發佈事件
     * @param string $routingKey 例如 'order.created', 'payment.success'
     * @param array $data 事件資料
     */
    public function publish(string $routingKey, array $data): void
    {
        $messageBody = json_encode([
            'event_id' => uniqid('evt_', true),
            'type' => $routingKey,
            'source' => 'event-gateway',
            'data' => $data,
            'occurred_at' => date(DATE_ISO8601)
        ]);

        $msg = new AMQPMessage($messageBody, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        $this->channel->basic_publish($msg, $this->exchangeName, $routingKey);
        echo "Example: Event Published -> Key: $routingKey\n";
    }
}