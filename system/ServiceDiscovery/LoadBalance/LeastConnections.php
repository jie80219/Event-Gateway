<?php
namespace AnserGateway\ServiceDiscovery\LoadBalance;
use AnserGateway\ServiceDiscovery\LoadBalance\LoadBalanceInterface;

class LeastConnections implements LoadBalanceInterface
{
    protected array $connections = [];
    protected array $services = [];

    public function do(array $services): array
    {
        $this->services = $services;

        foreach ($services as $service) {
            $address = $service['address'];
            if (!isset($this->connections[$address])) {
                $this->connections[$address] = 0;
            }
        }

        asort($this->connections);
        $leastUsedAddress = key($this->connections);
        $this->connections[$leastUsedAddress]++;

        return $this->getServiceByAddress($leastUsedAddress);
    }

    protected function getServiceByAddress(string $address): array
    {
        foreach ($this->services as $service) {
            if ($service['address'] === $address) {
                return $service;
            }
        }
        return [];
    }

    // 可選：實作釋放連線的方法
    public function release(string $address): void
    {
        if (isset($this->connections[$address]) && $this->connections[$address] > 0) {
            $this->connections[$address]--;
        }
    }
}
