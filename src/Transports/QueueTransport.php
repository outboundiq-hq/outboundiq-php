<?php

namespace OutboundIQ\Transports;

use OutboundIQ\Configuration;
use OutboundIQ\Contracts\MetricInterface;
use OutboundIQ\Models\ApiCall;

/**
 * Queue-based transport that dispatches metrics to a background job.
 * 
 * Use this transport when:
 * - Running on Laravel with queue workers (SQS, Redis, etc.)
 * - You want truly async behavior on Vapor/Lambda
 * - You have a queue worker running
 * 
 * Requires: A dispatcher callable to be set (Laravel sets this automatically)
 */
class QueueTransport implements TransportInterface
{
    /**
     * @var array<MetricInterface>
     */
    private array $queue = [];

    /**
     * @var Configuration
     */
    private Configuration $config;

    /**
     * @var callable|null
     */
    private static $dispatcher = null;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    /**
     * Set the dispatcher callable.
     * Laravel will set this to dispatch jobs.
     * 
     * @param callable $dispatcher Function that receives (array $metrics, Configuration $config)
     */
    public static function setDispatcher(callable $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    /**
     * Check if a dispatcher is configured.
     */
    public static function hasDispatcher(): bool
    {
        return self::$dispatcher !== null;
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

        if (!self::hasDispatcher()) {
            error_log('OutboundIQ: Queue transport requires a dispatcher. Use SyncTransport or AsyncTransport instead, or configure Laravel queue.');
            // Fallback to sync behavior
            $this->flushSync();
            return;
        }

        try {
            $data = array_map(fn($metric) => $metric->toArray(), $this->queue);
            
            // Dispatch to the queue via the configured dispatcher
            call_user_func(self::$dispatcher, $data, $this->config);
            
            $this->config->markFlushed();
        } catch (\Throwable $e) {
            error_log('OutboundIQ: Error dispatching to queue: ' . $e->getMessage());
        } finally {
            $this->resetQueue();
        }
    }

    /**
     * Fallback sync flush when no dispatcher is set.
     */
    private function flushSync(): void
    {
        try {
            $data = array_map(fn($metric) => $metric->toArray(), $this->queue);
            $jsonData = json_encode($data);
            
            if ($jsonData === false) {
                return;
            }

            $encodedData = base64_encode($jsonData);
            
            $handle = curl_init($this->config->getEndpoint());
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_TIMEOUT, $this->config->getTimeout());
            curl_setopt($handle, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->config->getApiKey(),
                'Content-Type: application/json',
                'User-Agent: OutboundIQ-PHP/' . $this->config->getVersion(),
            ]);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $encodedData);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_exec($handle);
            curl_close($handle);
            
            $this->config->markFlushed();
        } catch (\Throwable $e) {
            error_log('OutboundIQ: Sync fallback error: ' . $e->getMessage());
        } finally {
            $this->resetQueue();
        }
    }

    public function resetQueue(): void
    {
        $this->queue = [];
    }
}

