<?php
require __DIR__ . '/../../vendor/autoload.php';

use Swow\Http\Server;
use Swow\Http\Response;
use Swow\Http\Status;
use Swow\Coroutine;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// 1. 初始化 RabbitMQ 連線 (生產者)
$connection = new AMQPStreamConnection('rabbitmq', 5672, 'user', 'password');
$channel = $connection->channel();
// 宣告一個用於緩衝請求的 Queue，例如 'gateway_ingress'
$channel->queue_declare('gateway_ingress', false, true, false, false);

$server = new Server();
$server->bind('0.0.0.0', 8080)->listen();

echo "🚀 Ingress Server started on port 8080 (High Throughput Mode)...\n";

while ($connection = $server->accept()) {
    Coroutine::run(function () use ($connection, $channel) {
        try {
            $request = $connection->recvHttpRequest();
            
            // 2. 建立 Job Payload (包含 Headers, Body, Method)
            $payload = json_encode([
                'trace_id' => uniqid('req_', true), // 產生追蹤碼
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'body' => (string)$request->getBody(),
                'headers' => $request->getHeaders(),
                'timestamp' => time()
            ]);

            // 3. 快速寫入 Queue (Fire and Forget)
            $msg = new AMQPMessage($payload, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
            $channel->basic_publish($msg, '', 'gateway_ingress');

            // 4. 回應 HTTP 202 Accepted
            $response = new Response();
            $response->setStatus(Status::ACCEPTED); // 202
            $response->setHeader('Content-Type', 'application/json');
            $response->setBody(json_encode([
                'status' => 'accepted',
                'message' => 'Request is being processed.',
                'trace_id' => json_decode($payload)->trace_id
            ]));
            $connection->sendHttpResponse($response);

        } catch (Throwable $e) {
            // 錯誤處理 (略)
            echo "Error: " . $e->getMessage() . "\n";
        } finally {
            $connection->close();
        }
    });
}