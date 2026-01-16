<?php

namespace OutboundIQ\Transports;

use OutboundIQ\Configuration;
use OutboundIQ\Contracts\MetricInterface;
use OutboundIQ\Models\ApiCall;

class QueueTransport implements TransportInterface
{
    private array $queue = [];
    private Configuration $config;
    private static $dispatcher = null;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public static function setDispatcher(callable $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    public static function hasDispatcher(): bool
    {
        return self::$dispatcher !== null;
    }

    public function addMetric(MetricInterface $metric): void
    {
        if (!$metric instanceof ApiCall) {
            throw new \InvalidArgumentException('Invalid metric type');
        }

        $data = $metric->toArray();
        if (empty($data['url']) || empty($data['method'])) {
            return;
        }

        $this->queue[] = $metric;
        
        if ($this->config->shouldFlush(count($this->queue))) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->queue)) {
            return;
        }

        if (!self::hasDispatcher()) {
            $this->flushSync();
            return;
        }

        try {
            $data = array_map(fn($metric) => $metric->toArray(), $this->queue);
            call_user_func(self::$dispatcher, $data, $this->config);
            $this->config->markFlushed();
        } catch (\Throwable $e) {
            error_log('OutboundIQ: ' . $e->getMessage());
        } finally {
            $this->resetQueue();
        }
    }

    private function flushSync(): void
    {
        try {
            $data = array_map(fn($metric) => $metric->toArray(), $this->queue);
            $jsonData = json_encode($data);
            
            if ($jsonData === false) {
                return;
            }

            $handle = curl_init($this->config->getEndpoint());
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_TIMEOUT => $this->config->getTimeout(),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->config->getApiKey(),
                    'Content-Type: application/json',
                    'User-Agent: OutboundIQ-PHP/' . $this->config->getVersion(),
                ],
                CURLOPT_POSTFIELDS => base64_encode($jsonData),
                CURLOPT_RETURNTRANSFER => true,
            ]);
            curl_exec($handle);
            curl_close($handle);
            
            $this->config->markFlushed();
        } catch (\Throwable $e) {
            error_log('OutboundIQ: ' . $e->getMessage());
        } finally {
            $this->resetQueue();
        }
    }

    public function resetQueue(): void
    {
        $this->queue = [];
    }
}
