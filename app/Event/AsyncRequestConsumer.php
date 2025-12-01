<?php namespace App\Event;

use Workerman\Worker;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AsyncRequestConsumer
{
    public function onWorkerStart($worker)
    {
        $connection = new AMQPStreamConnection(
            getenv('MQ_HOST') ?: 'rabbitmq',
            getenv('MQ_PORT') ?: 5672,
            getenv('MQ_USER') ?: 'guest',
            getenv('MQ_PASS') ?: 'guest'
        );
        $channel = $connection->channel();

        // 確保與 Gateway 設定一致
        $exchange = 'api.ingress';
        $queue = 'gateway.requests';

        $channel->exchange_declare($exchange, 'direct', false, true, false);
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, $exchange, 'request.create');

        echo " [*] Waiting for Async Requests in $queue. \n";

        $callback = function ($msg) {
            $body = json_decode($msg->body, true);
            echo ' [x] Received Request: ', $body['request_id'], "\n";

            // *** 這裡觸發你的 Saga 邏輯 ***
            try {
                if ($body['event_type'] === 'ORDER_CREATE') {
                    // 例如：呼叫既有的 OrderSaga
                    // $saga = new \App\Sagas\OrderSaga();
                    // $saga->start($body['data']);
                    echo " [v] Saga Started for {$body['request_id']}\n";
                }
                
                // 只有成功才 Ack
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            } catch (\Throwable $e) {
                echo " [!] Error processing: " . $e->getMessage() . "\n";
                // 失敗則 Nack (根據策略可能需要重試或丟入 Dead Letter Queue)
                // $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], false, true);
            }
        };

        $channel->basic_qos(null, 1, null); // 每次只拿一個任務 (公平分發)
        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        // 使用 Workerman 的事件循環來監聽 MQ
        // 注意：這裡是一個簡化的整合，通常會將 AMQP 的 wait 放入 Loop 中
        while (count($channel->callbacks)) {
            $channel->wait();
        }
    }
}