<?php

namespace OutboundIQ\Transports;

use OutboundIQ\Configuration;
use OutboundIQ\Contracts\MetricInterface;
use OutboundIQ\Models\ApiCall;

/**
 * Synchronous transport using curl_exec.
 * 
 * Use this transport when:
 * - Running on Laravel Vapor / AWS Lambda
 * - proc_open is not available
 * - You need guaranteed delivery (blocking)
 * 
 * Trade-off: Adds ~50-100ms latency per flush
 */
class SyncTransport implements TransportInterface
{
    /**
     * @var array<MetricInterface>
     */
    private array $queue = [];

    /**
     * @var Configuration
     */
    private Configuration $config;

    public function __construct(Configuration $config)
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('curl extension is required for SyncTransport');
        }
        $this->config = $config;
    }

    public function addMetric(MetricInterface $metric): void
    {
        if (!$metric instanceof ApiCall) {
            throw new \InvalidArgumentException('Invalid metric type. Expected ApiCall instance.');
        }

        $data = $metric->toArray();
        if (empty($data['url']) || empty($data['method'])) {
            error_log('OutboundIQ: Invalid metric data - missing required fields');
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
                throw new \RuntimeException('Failed to encode metrics data: ' . json_last_error_msg());
            }

            $encodedData = base64_encode($jsonData);
            $this->sendChunk($encodedData);
            $this->config->markFlushed();
        } catch (\Throwable $e) {
            error_log('OutboundIQ: Error sending metrics: ' . $e->getMessage());
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

        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->config->getTimeout());
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config->getApiKey(),
            'Content-Type: application/json',
            'User-Agent: OutboundIQ-PHP/' . $this->config->getVersion(),
        ]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($handle);
        $errorNo = curl_errno($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);

        if ($errorNo !== 0 || ($httpCode !== 200 && $httpCode !== 201)) {
            error_log("OutboundIQ: Sync transport error - HTTP $httpCode, curl error: $error ($errorNo)");
        }

        curl_close($handle);
    }
}

