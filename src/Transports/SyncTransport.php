<?php

namespace OutboundIQ\Transports;

use OutboundIQ\Configuration;
use OutboundIQ\Contracts\MetricInterface;
use OutboundIQ\Models\ApiCall;

/**
 * Blocking transport using curl_exec.
 * Use for Laravel Vapor / AWS Lambda where proc_open doesn't work.
 */
class SyncTransport implements TransportInterface
{
    private array $queue = [];
    private Configuration $config;

    public function __construct(Configuration $config)
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('curl extension is required');
        }
        $this->config = $config;
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

        try {
            $data = array_map(fn($metric) => $metric->toArray(), $this->queue);
            $jsonData = json_encode($data);
            
            if ($jsonData === false) {
                throw new \RuntimeException('Failed to encode metrics: ' . json_last_error_msg());
            }

            $this->sendChunk(base64_encode($jsonData));
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

    private function sendChunk(string $data): void
    {
        $handle = curl_init($this->config->getEndpoint());

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->config->getTimeout(),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config->getApiKey(),
                'Content-Type: application/json',
                'User-Agent: OutboundIQ-PHP/' . $this->config->getVersion(),
            ],
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        curl_exec($handle);
        curl_close($handle);
    }
}
