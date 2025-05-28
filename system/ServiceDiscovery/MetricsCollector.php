<?php

namespace AnserGateway\ServiceDiscovery;

use GuzzleHttp\Client;

class MetricsCollector
{
    protected string $consulBaseUri;
    protected string $serviceName;
    protected array $cachedMetrics = [];
    protected int $cacheDuration; // 秒
    protected ?int $lastFetchTime = null;
    protected Client $httpClient;

    public function __construct(string $consulBaseUri, string $serviceName, int $cacheDuration = 10)
    {
        $this->consulBaseUri = rtrim($consulBaseUri, '/');
        $this->serviceName = $serviceName;
        $this->cacheDuration = $cacheDuration;
        $this->httpClient = new Client();
    }

    /**
     * 從 Consul 取得服務即時監控指標（此處為範例，實際可依需求擴充）
     */
    protected function fetchMetrics(): void
    {
        $url = "{$this->consulBaseUri}/v1/health/service/{$this->serviceName}?passing=true";

        try {
            $response = $this->httpClient->get($url);
            if ($response->getStatusCode() !== 200) {
                return;
            }

            $data = json_decode($response->getBody()->getContents(), true);

            $metrics = [];
            foreach ($data as $entry) {
                $service = $entry['Service'];
                $metrics[] = [
                    'id' => $service['ID'],
                    'address' => $service['Address'],
                    'port' => $service['Port'],
                    'tags' => $service['Tags'],
                    'meta' => $service['Meta'] ?? [],
                    'check_status' => $entry['Checks'][0]['Status'] ?? 'unknown',
                    // 可擴充：自訂指標，如連線數、回應時間（需從 meta 或其他系統蒐集）
                ];
            }
            $this->cachedMetrics = $metrics;
            $this->lastFetchTime = time();
        } catch (\Exception $e) {
            // Log 或錯誤處理
        }
    }

    /**
     * 取得即時監控指標，帶快取機制避免頻繁請求
     */
    public function getMetrics(): array
    {
        if ($this->lastFetchTime === null || (time() - $this->lastFetchTime) > $this->cacheDuration) {
            $this->fetchMetrics();
        }
        return $this->cachedMetrics;
    }
}
