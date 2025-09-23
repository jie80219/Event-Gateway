<?php
namespace SDPMlab\AnserGateway\Util;

use Redis;

class RedisClient
{
    private static ?Redis $instance = null;
    private const HOST = '10.1.1.94';
    private const PORT = 6379;
    private const DB   = 0; // 明確指定使用 DB0

    public static function get(): Redis
    {
        if (self::$instance === null) {
            self::$instance = new Redis();
            try {
                self::$instance->connect(self::HOST, self::PORT);
                self::$instance->select(self::DB);
                error_log("[RedisClient] Connected to Redis at " . self::HOST . ":" . self::PORT . " (DB " . self::DB . ")");
            } catch (\RedisException $e) {
                error_log("[RedisClient] Connection failed: " . $e->getMessage());
                throw $e;
            }
        }
        return self::$instance;
    }
}
