<?php
require __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use App\Component\EventBus;

// 1. 初始化連線
$connection = new AMQPStreamConnection('rabbitmq', 5672, 'user', 'password');
$channel = $connection->channel();
$channel->queue_declare('gateway_ingress', false, true, false, false);

// 2. 初始化 EventBus (發佈到 Topic Exchange)
$eventBus = new EventBus($channel, 'saga_events');

// 3. 設定 QoS (流量削峰關鍵!)
// 一次只拿 10 個任務，處理完才拿新的。這保護了後端服務不被壓垮。
$channel->basic_qos(null, 10, null);

echo "⚙️  Worker started. Waiting for requests...\n";

// 定義回調函數
$callback = function ($msg) use ($eventBus) {
    echo " [x] Received request\n";
    
    // 解析原始 HTTP 請求
    $rawRequest = json_decode($msg->body, true);
    
    // --- 這裡整合 Anser-Gateway 邏輯 ---
    // 1. 驗證請求 (Auth check, Params check)
    // 2. 決定路由 (你是要建立訂單? 還是查詢?)
    
    // 假設這是一個建立訂單的請求
    if ($rawRequest['path'] === '/api/create-order' && $rawRequest['method'] === 'POST') {
        
        $orderData = json_decode($rawRequest['body'], true);
        
        // --- 這裡整合 Anser-EDA / Saga ---
        // 不直接寫資料庫，而是發佈事件觸發 Saga
        
        echo " [->] Triggering Saga for Order...\n";
        
        // 發佈事件：order.create
        // Saga Orchestrator 會訂閱這個 Topic
        $eventBus->publish('order.create', [
            'trace_id' => $rawRequest['trace_id'],
            'user_id' => $orderData['user_id'],
            'items' => $orderData['items'],
            'amount' => $orderData['amount']
        ]);
        
    } else {
        echo " [!] Unknown path or method.\n";
    }

    // 4. 手動 ACK (告訴 RabbitMQ 任務已完成)
    $msg->ack();
};

// 開始消費
$channel->basic_consume('gateway_ingress', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();