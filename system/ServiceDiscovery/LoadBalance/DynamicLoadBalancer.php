<?php
namespace AnserGateway\ServiceDiscovery\LoadBalance;

use AnserGateway\ServiceDiscovery\LoadBalance\LoadBalanceInterface;
use SDPMlab\AnserGateway\ServiceDiscovery\LoadBalance\CachedScoreManager;

class DynamicLoadBalancer implements LoadBalanceInterface
{
    // 預定義好 host 映射
    private array $addressToHost = [
        "10.1.1.91" => "production-1",
        "10.1.1.90" => "production-2",
        "10.1.1.92" => "production-3",
    ];

    /**
     * 根據服務的 IP 地址選擇伺服器
     *
     * @param array $services [['address' => '10.1.1.x', ...], ...]
     * @return array
     */
    public function do(array $services): array
    {
        $scores = [];
        $scoreManager = CachedScoreManager::getInstance();

        foreach ($services as $service) {
            $ip = $service['address'];  // 取得服務的 IP 地址
            $host = $this->addressToHost[$ip] ?? null;  // 根據 IP 查找對應的 host

            // 如果找不到對應的 host，跳過該服務
            if ($host === null) {
                error_log("[DynamicLoadBalancer] Unknown IP $ip");
                continue;
            }

            // 根據 host 從 score manager 取得分數
            $score = $scoreManager->getScore($host);
            if ($score !== null) {
                $scores[] = [
                    'service' => $service,  // 存儲該服務
                    'score' => $score,      // 對應的分數
                ];
            }
        }

        // 如果沒有可用的服務，隨機返回一個
        if (empty($scores)) {
            error_log("[DynamicLoadBalancer] No usable scores, fallback to random");
            return $services[array_rand($services)];
        }

        // 根據分數加權隨機選擇服務
        $total = array_sum(array_column($scores, 'score'));
        $rand = mt_rand() / mt_getrandmax();
        $acc = 0;

        // 根據分數加權選擇
        foreach ($scores as $entry) {
            $acc += $entry['score'] / $total;
            if ($rand <= $acc) {
                return $entry['service'];  // 返回選中的服務
            }
        }

        // 默認情況下隨機返回服務
        return $services[array_rand($services)];
    }
}
