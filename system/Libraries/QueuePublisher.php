<?php namespace System\Libraries;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class QueuePublisher
{
    protected $connection;
    protected $channel;
    protected $exchange = 'api.ingress';
    protected $queue = 'gateway.requests';

    public function __construct()
    {
        // 請確保這些設定寫入 .env
        $this->connection = new AMQPStreamConnection(
            getenv('MQ_HOST') ?: 'rabbitmq',
            getenv('MQ_PORT') ?: 5672,
            getenv('MQ_USER') ?: 'guest',
            getenv('MQ_PASS') ?: 'guest'
        );
        $this->channel = $this->connection->channel();

        // 宣告 Exchange 和 Queue (確保它們存在)
        $this->channel->exchange_declare($this->exchange, 'direct', false, true, false);
        $this->channel->queue_declare($this->queue, false, true, false, false);
        $this->channel->queue_bind($this->queue, $this->exchange, 'request.create');
    }

    public function publish($data, $routingKey = 'request.create')
    {
        $msgBody = json_encode($data);
        $msg = new AMQPMessage($msgBody, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // 確保訊息持久化
            'content_type'  => 'application/json'
        ]);

        $this->channel->basic_publish($msg, $this->exchange, $routingKey);
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}