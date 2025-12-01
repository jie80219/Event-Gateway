<?php namespace App\HTTP\Controllers;

use System\Controller;
use System\Libraries\QueuePublisher;

class AsyncIngress extends Controller
{
    public function createOrder()
    {
        // 1. 獲取並驗證請求
        $json = $this->request->getJSON(true);
        if (empty($json)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No data provided']);
        }

        // 2. 產生追蹤 ID (Request ID)
        $requestId = uniqid('req_', true);
        $payload = [
            'event_type' => 'ORDER_CREATE',
            'request_id' => $requestId,
            'timestamp'  => time(),
            'data'       => $json
        ];

        try {
            // 3. 推送到 RabbitMQ
            $publisher = new QueuePublisher();
            $publisher->publish($payload);
            $publisher->close();

            // 4. 立即返回 202 Accepted
            return $this->response->setStatusCode(202)->setJSON([
                'status' => 'accepted',
                'message' => 'Request queued for processing',
                'request_id' => $requestId
            ]);

        } catch (\Exception $e) {
            log_message('error', '[Queue] Publish failed: ' . $e->getMessage());
            return $this->response->setStatusCode(503)->setJSON(['error' => 'Service unavailable']);
        }
    }
}