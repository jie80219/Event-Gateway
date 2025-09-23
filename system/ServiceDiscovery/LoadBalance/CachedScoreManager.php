<?php
namespace SDPMlab\AnserGateway\ServiceDiscovery\LoadBalance;

use Workerman\Timer;

class CachedScoreManager
{
    private static ?CachedScoreManager $instance = null;
    private array $scoreCache = [];

    private function __construct()
    {
        $this->startDebugMonitor();   // 啟動時自動開始監控與同步快取
    }

    public static function getInstance(): CachedScoreManager
    {
        if (self::$instance === null) {
            self::$instance = new CachedScoreManager();
        }
        return self::$instance;
    }

    public function getScore(string $host): ?float
    {
        return $this->scoreCache[$host] ?? null;
    }

    public function getAllScores(): array
    {
        return $this->scoreCache;
    }

    public function updateCacheDirectly(array $scores): void
    {
        $this->scoreCache = $scores;
        error_log("[CachedScoreManager] Cache updated directly: " . json_encode($scores));
    }

    private function startDebugMonitor(): void
    {
        // 每個 Worker 啟動時都會建立自己的定時任務（每 5 秒）
        Timer::add(5, function () {
            try {
                $redis = new \Redis();
                $redis->connect('10.1.1.94', 6379);
                $scores = $redis->hGetAll("metrics:anser-gateway");

                if (!empty($scores)) {
                    foreach ($scores as $host => $score) {
                        $this->scoreCache[$host] = (float)$score;
                    }
                    error_log("[CachedScoreManager] [" . date('H:i:s') . "] Cache snapshot: " . json_encode($this->scoreCache));
                } else {
                    error_log("[CachedScoreManager] [" . date('H:i:s') . "] Cache snapshot: EMPTY");
                }

                $redis->close();
            } catch (\Exception $e) {
                error_log("[CachedScoreManager] Redis error during monitor: " . $e->getMessage());
            }
        });
    }
}
