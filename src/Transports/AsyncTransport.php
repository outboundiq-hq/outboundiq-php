<?php

namespace OutboundIQ\Transports;

use OutboundIQ\Configuration;
use OutboundIQ\Contracts\MetricInterface;
use OutboundIQ\Models\ApiCall;

class AsyncTransport implements TransportInterface
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
        if (!\function_exists('proc_open')) {
            throw new \RuntimeException('proc_open function is required but not available');
        }
        $this->config = $config;
    }

    public function addMetric(MetricInterface $metric): void
    {
        if (!$metric instanceof ApiCall) {
            throw new \InvalidArgumentException('Invalid metric type. Expected ApiCall instance.');
        }

        // Validate required fields
        $data = $metric->toArray();
        if (empty($data['url']) || empty($data['method'])) {
            error_log('OutboundIQ: Invalid metric data - missing required fields');
            return;
        }

        $this->queue[] = $metric;
        
        // Check if we should flush based on buffer size or time interval
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
            // Convert metrics to array format
            $data = array_map(function ($metric) {
                $array = $metric->toArray();
                if (empty($array['url']) || empty($array['method'])) {
                    throw new \RuntimeException('Invalid metric data: missing required fields');
                }
                return $array;
            }, $this->queue);

            $jsonData = json_encode($data);
            
            if ($jsonData === false) {
                throw new \RuntimeException('Failed to encode metrics data: ' . json_last_error_msg());
            }

            // Base64 encode the JSON data
            $encodedData = base64_encode($jsonData);
            
            // If data is too large, use file transport
            if (strlen($encodedData) > $this->config->getMaxPayloadSize()) {
                $tmpfile = $this->writeToTempFile($encodedData);
                $this->sendChunk('@' . $tmpfile);
            } else {
                $this->sendChunk($encodedData);
            }

            // Mark the flush time
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


    private function writeToTempFile(string $data): string
    {
        $tmpfile = tempnam(sys_get_temp_dir(), 'outboundiq_');
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
            
            // Delete temporary file after data transfer
            if (substr($data, 0, 1) === '@') {
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
        
        // Add headers
        foreach ($headers as $header) {
            $cmd .= ' -H ' . escapeshellarg($header);
        }
        
        // Add timeout
        $cmd .= ' --max-time ' . $this->config->getTimeout();
        
        // Add retry attempts
        $cmd .= ' --retry ' . $this->config->getRetryAttempts();
        
        // Add data
        if (substr($data, 0, 1) === '@') {
            // File-based data
            $cmd .= ' --data-binary ' . escapeshellarg($data);
        } else {
            // Direct data
            $cmd .= ' --data ' . escapeshellarg($data);
        }
        
        // Add endpoint
        $cmd .= ' ' . escapeshellarg($this->config->getEndpoint());
        
        return $cmd;
    }
} 