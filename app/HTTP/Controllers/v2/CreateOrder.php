<?php

namespace App\Controllers\v2;

use App\Controllers\BaseController;
use SDPMlab\Anser\Orchestration\Saga\SimpleSaga;
use App\Event\OrderCreateRequestedEvent; // 你上傳的事件類別
use Workerman\Protocols\Http\Response;

class CreateOrder extends BaseController
{
    public function index()
    {
        // 1. 取得請求資料
        $data = $this->request->getPost();
        $orderId = uniqid('ORD-');

        // 2. 定義 Saga (這裡使用 SimpleSaga 做示範，建議建立獨立 Saga 類別)
        // 在 Event-Gateway 模式下，Gateway 通常負責 "發起" 事件
        
        try {
            $saga = new SimpleSaga();
            
            // 步驟 1: 發布「訂單建立請求」事件
            // 這是 Choreography 的起手式，Gateway 告訴大家 "有人想買東西"
            $saga->setStep('publish_create_event', function($runtimeData) use ($data, $orderId) {
                $event = new OrderCreateRequestedEvent();
                $event->data = [
                    'order_id' => $orderId,
                    'user_id'  => $data['user_id'],
                    'products' => $data['products'],
                    'amount'   => $data['amount']
                ];
                // 發布事件到 MessageBus
                $event->publish(); 
            });

            // 3. 啟動 Saga
            // 在 Swow/Workerman 環境下，這會是非阻塞的
            $saga->start();

            // 4. 回應客戶端
            // 由於是 EDA，我們通常回傳 202 Accepted，代表請求已收，處理中
            return $this->response->setJSON([
                'status'  => 'accepted',
                'message' => 'Order creation process started.',
                'data'    => ['order_id' => $orderId],
                'trace_id' => $saga->getOrchestratorNumber() // 這是重要的可觀測性 ID
            ])->setStatusCode(202);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }
}