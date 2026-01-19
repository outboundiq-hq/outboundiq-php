<?php

namespace OutboundIQ;

use OutboundIQ\Exceptions\ConfigurationException;

class Configuration
{
    private const DEFAULTS = [
        'batch_size' => 50,
        'enabled' => true,
        'timeout' => 5,
        'retry_attempts' => 3,
        'buffer_size' => 100,
        'flush_interval' => 30,
        'max_payload_size' => 65536,
        'max_concurrent_requests' => 10,
        'transport' => 'async',
        'temp_dir' => null,
    ];

    private const PROTECTED_PROPERTIES = ['version', 'max_payload_size', 'max_concurrent_requests'];

    private const DEFAULT_ENDPOINT = 'https://agent.outboundiq.dev/api/metric';

    private array $options;
    private ?string $apiKey;
    private string $endpoint;
    private int $maxPayloadSize;
    private int $maxConcurrentRequests;
    private string $tempDir;
    private string $transport;
    private float $lastFlushTime;
    private int $bufferSize;
    private int $flushInterval;
    private string $version = '1.0.0';
    private int $timeout;
    private int $retryAttempts;
    private bool $enabled;

    public function __construct(?string $apiKey = null, array $options = [])
    {
        $this->apiKey = $apiKey ?? '';
        $this->enabled = $apiKey !== null && ($options['enabled'] ?? true);
        
        $config = array_merge(self::DEFAULTS, $options);
        
        if ($config['temp_dir'] === null) {
            $config['temp_dir'] = sys_get_temp_dir();
        }

        if ($config['transport'] === 'async') {
            $tempDir = $config['temp_dir'];
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0755, true);
            }
            if (!is_dir($tempDir) || !is_writable($tempDir)) {
                $config['temp_dir'] = sys_get_temp_dir();
            }
        }

        $this->maxPayloadSize = $this->validateMaxPayloadSize($config['max_payload_size']);
        $this->maxConcurrentRequests = $this->validateMaxConcurrentRequests($config['max_concurrent_requests']);
        $this->bufferSize = max(1, $config['buffer_size']);
        $this->flushInterval = max(1, $config['flush_interval']);
        $this->timeout = max(1, $config['timeout']);
        $this->retryAttempts = max(0, $config['retry_attempts']);
        $this->transport = $config['transport'];
        $this->tempDir = $config['temp_dir'];
        $this->lastFlushTime = microtime(true);
        $this->endpoint = isset($options['url']) ? $this->validateEndpoint($options['url']) : self::DEFAULT_ENDPOINT;
        $this->options = $config;

        if (!empty($this->apiKey)) {
            $this->validateApiKey($this->apiKey);
        }
    }

    private function validateApiKey(?string $apiKey): void
    {
        if ($apiKey === null) {
            return;
        }
        if (empty($apiKey)) {
            throw new ConfigurationException('API key cannot be empty');
        }
        if (strlen($apiKey) < 32) {
            throw new ConfigurationException('Invalid API key format');
        }
    }

    private function validateEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if (empty($endpoint) || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            throw new ConfigurationException('Invalid endpoint URL');
        }
        return $endpoint;
    }

    private function validateMaxPayloadSize(int $size): int
    {
        $minSize = 1024;
        $maxSize = 10485760;
        if ($size < $minSize || $size > $maxSize) {
            throw new ConfigurationException("Max payload size must be between {$minSize} and {$maxSize} bytes");
        }
        return $size;
    }

    private function validateMaxConcurrentRequests(int $count): int
    {
        $minCount = 1;
        $maxCount = 50;
        if ($count < $minCount || $count > $maxCount) {
            throw new ConfigurationException("Max concurrent requests must be between {$minCount} and {$maxCount}");
        }
        return $count;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setUrl(string $url): self
    {
        $this->endpoint = $this->validateEndpoint($url);
        return $this;
    }

    public function getBaseUrl(): string
    {
        return str_replace('/metric', '', $this->endpoint);
    }

    public function getBatchSize(): int
    {
        return $this->options['batch_size'];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getRetryAttempts(): int
    {
        return $this->retryAttempts;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getMaxPayloadSize(): int
    {
        return $this->maxPayloadSize;
    }

    public function getMaxConcurrentRequests(): int
    {
        return $this->maxConcurrentRequests;
    }

    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function setTransport(string $transport): self
    {
        $this->transport = $transport;
        return $this;
    }

    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    public function setBufferSize(int $size): self
    {
        $this->bufferSize = max(1, $size);
        return $this;
    }

    public function getFlushInterval(): int
    {
        return $this->flushInterval;
    }

    public function setFlushInterval(int $seconds): self
    {
        $this->flushInterval = max(1, $seconds);
        return $this;
    }

    public function setMaxPayloadSize(int $bytes): self
    {
        $this->maxPayloadSize = max(1024, $bytes);
        return $this;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    public function setRetryAttempts(int $attempts): self
    {
        $this->retryAttempts = max(0, $attempts);
        return $this;
    }

    public function shouldFlush(int $queueSize): bool
    {
        $timeSinceLastFlush = microtime(true) - $this->lastFlushTime;
        return $queueSize >= $this->bufferSize || $timeSinceLastFlush >= $this->flushInterval;
    }

    public function markFlushed(): void
    {
        $this->lastFlushTime = microtime(true);
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function set(string $key, mixed $value): self
    {
        if (in_array($key, self::PROTECTED_PROPERTIES)) {
            throw new ConfigurationException("Cannot modify protected property: {$key}");
        }

        match ($key) {
            'buffer_size' => $this->setBufferSize($value),
            'flush_interval' => $this->setFlushInterval($value),
            'timeout' => $this->setTimeout($value),
            'retry_attempts' => $this->setRetryAttempts($value),
            'enabled' => $this->setEnabled($value),
            'transport' => $this->setTransport($value),
            'url' => $this->setUrl($value),
            default => throw new ConfigurationException("Unknown configuration key: {$key}"),
        };

        return $this;
    }
}
