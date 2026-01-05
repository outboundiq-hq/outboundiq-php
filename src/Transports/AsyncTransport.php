<?php

namespace OutboundIQ\Transports;

use OutboundIQ\Configuration;
use OutboundIQ\Contracts\MetricInterface;
use OutboundIQ\Models\ApiCall;

/**
 * Non-blocking transport using background curl process.
 * Best for traditional servers (Forge, DigitalOcean, etc.)
 */
class AsyncTransport implements TransportInterface
{
    private array $queue = [];
    private Configuration $config;

    public function __construct(Configuration $config)
    {
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('proc_open function is required but not available');
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

            $encodedData = base64_encode($jsonData);
            
            if (strlen($encodedData) > $this->config->getMaxPayloadSize()) {
                $tmpfile = $this->writeToTempFile($encodedData);
                $this->sendChunk('@' . $tmpfile);
            } else {
                $this->sendChunk($encodedData);
            }

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

    private function writeToTempFile(string $data): string
    {
        $tmpfile = tempnam($this->config->getTempDir(), 'oiq_');
        file_put_contents($tmpfile, $data, LOCK_EX);
        return $tmpfile;
    }

    private function sendChunk(string $data): void
    {
        $cmd = $this->buildCurlCommand($data);
        
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start /B ' . $cmd . ' > NUL';
        } else {
            $cmd = '(' . $cmd . ' > /dev/null 2>&1';
            if (str_starts_with($data, '@')) {
                $cmd .= '; rm ' . str_replace('@', '', $data);
            }
            $cmd .= ')&';
        }
        
        proc_close(proc_open($cmd, [], $pipes));
    }

    private function buildCurlCommand(string $data): string
    {
        $headers = [
            'Authorization: Bearer ' . $this->config->getApiKey(),
            'Content-Type: application/json',
            'User-Agent: OutboundIQ-PHP/' . $this->config->getVersion()
        ];
        
        $cmd = 'curl -X POST --ipv4';
        
        foreach ($headers as $header) {
            $cmd .= ' -H ' . escapeshellarg($header);
        }
        
        $cmd .= ' --max-time ' . $this->config->getTimeout();
        $cmd .= ' --retry ' . $this->config->getRetryAttempts();
        
        if (str_starts_with($data, '@')) {
            $cmd .= ' --data-binary ' . escapeshellarg($data);
        } else {
            $cmd .= ' --data ' . escapeshellarg($data);
        }
        
        $cmd .= ' ' . escapeshellarg($this->config->getEndpoint());
        
        return $cmd;
    }
}
