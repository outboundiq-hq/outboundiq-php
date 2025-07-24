<?php

namespace OutboundIQ;

use OutboundIQ\Exceptions\ConfigurationException;

class Configuration
{
    /**
     * Default configuration options
     *
     * @var array
     */
    private const array DEFAULTS = [
        'batch_size' => 50,
        'enabled' => true,
        'timeout' => 5,
        'retry_attempts' => 3,
        'buffer_size' => 100,
        'flush_interval' => 60,
        'max_payload_size' => 65536,  // 64KB
        'max_concurrent_requests' => 10,
        'transport' => 'file',
        'temp_dir' => null  // Will be set to sys_get_temp_dir() in constructor
    ];

    /**
     * Properties that cannot be modified after initialization
     *
     * @var array
     */
    private const array PROTECTED_PROPERTIES = [
        'version',
        'max_payload_size',
        'max_concurrent_requests'
    ];

   // private const ENDPOINT = 'https://webhook.site/75c0dfc9-d25c-4ac9-9523-dcb02000cb87';
    private const string ENDPOINT = 'http://agent.outboundiq.test/api/metric';

    /**
     * Current configuration options
     *
     * @var array
     */
    private array $options;

    /**
     * API key for authentication
     *
     * @var string|null
     */
    private ?string $apiKey;

    private int $maxPayloadSize;
    private int $maxConcurrentRequests;
    private string $tempDir;
    private string $transport;
    private float $lastFlushTime;
    private int $bufferSize;
    private int $flushInterval;
    private string $version = '1.0.0';  // Version is hardcoded and not configurable
    private int $timeout;
    private int $retryAttempts;
    private bool $enabled;

    /**
     * Initialize configuration
     *
     * @param string|null $apiKey OutboundIQ API key
     * @param array $options Configuration options
     * @throws ConfigurationException
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $this->apiKey = $apiKey ?? '';
        $this->enabled = $apiKey !== null && ($options['enabled'] ?? true);
        
        // Merge options with defaults
        $config = array_merge(self::DEFAULTS, $options);
        
        // Set temp_dir default if not provided
        if ($config['temp_dir'] === null) {
            $config['temp_dir'] = sys_get_temp_dir();
        }

        // Validate temp directory
        if (!is_dir($config['temp_dir']) || !is_writable($config['temp_dir'])) {
            throw new ConfigurationException("Temporary directory {$config['temp_dir']} is not writable");
        }

        // Initialize properties from merged config
        $this->maxPayloadSize = $this->validateMaxPayloadSize($config['max_payload_size']);
        $this->maxConcurrentRequests = $this->validateMaxConcurrentRequests($config['max_concurrent_requests']);
        $this->bufferSize = max(1, $config['buffer_size']);
        $this->flushInterval = max(1, $config['flush_interval']);
        $this->timeout = max(1, $config['timeout']);
        $this->retryAttempts = max(0, $config['retry_attempts']);
        $this->transport = $config['transport'];
        $this->tempDir = $config['temp_dir'];
        $this->lastFlushTime = microtime(true);
        
        // Store the complete configuration
        $this->options = $config;

        if (!empty($this->apiKey)) {
            $this->validateApiKey($this->apiKey);
        }
    }

    /**
     * Validate API key format
     *
     * @param string|null $apiKey
     * @throws ConfigurationException
     */
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

    /**
     * Validate and normalize the endpoint URL
     *
     * @param string $endpoint
     * @return string
     * @throws ConfigurationException
     */
    private function validateEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if (empty($endpoint) || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            throw new ConfigurationException('Invalid endpoint URL');
        }
        return $endpoint;
    }

    /**
     * Validate max payload size
     *
     * @param int $size
     * @return int
     * @throws ConfigurationException
     */
    private function validateMaxPayloadSize(int $size): int
    {
        $minSize = 1024;     // 1KB
        $maxSize = 10485760; // 10MB
        
        if ($size < $minSize || $size > $maxSize) {
            throw new ConfigurationException(
                "Max payload size must be between {$minSize} and {$maxSize} bytes"
            );
        }
        
        return $size;
    }

    /**
     * Validate max concurrent requests
     *
     * @param int $count
     * @return int
     * @throws ConfigurationException
     */
    private function validateMaxConcurrentRequests(int $count): int
    {
        $minCount = 1;
        $maxCount = 50;
        
        if ($count < $minCount || $count > $maxCount) {
            throw new ConfigurationException(
                "Max concurrent requests must be between {$minCount} and {$maxCount}"
            );
        }
        
        return $count;
    }

    /**
     * Get API key
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Get the endpoint URL
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return self::ENDPOINT;
    }

    /**
     * Get batch size
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        return $this->options['batch_size'];
    }

    /**
     * Check if monitoring is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get timeout setting
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get retry attempts
     *
     * @return int
     */
    public function getRetryAttempts(): int
    {
        return $this->retryAttempts;
    }

    /**
     * Get package version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get maximum payload size in bytes
     *
     * @return int
     */
    public function getMaxPayloadSize(): int
    {
        return $this->maxPayloadSize;
    }

    /**
     * Get maximum concurrent requests
     *
     * @return int
     */
    public function getMaxConcurrentRequests(): int
    {
        return $this->maxConcurrentRequests;
    }

    /**
     * Get temporary directory
     *
     * @return string
     */
    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    /**
     * Get all configuration options
     *
     * @return array
     */
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
        $this->bufferSize = max(1, $size); // Ensure minimum of 1
        return $this;
    }

    public function getFlushInterval(): int
    {
        return $this->flushInterval;
    }

    public function setFlushInterval(int $seconds): self
    {
        $this->flushInterval = max(1, $seconds); // Ensure minimum of 1 second
        return $this;
    }

    public function setMaxPayloadSize(int $bytes): self
    {
        $this->maxPayloadSize = max(1024, $bytes); // Ensure minimum of 1KB
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

    /**
     * Set a configuration value
     *
     * @param string $key
     * @param mixed $value
     * @return self
     * @throws ConfigurationException
     */
    public function set(string $key, mixed $value): self
    {
        if (in_array($key, self::PROTECTED_PROPERTIES)) {
            throw new ConfigurationException("Cannot modify protected property: {$key}");
        }

        switch ($key) {
            case 'buffer_size':
                $this->setBufferSize($value);
                break;
            case 'flush_interval':
                $this->setFlushInterval($value);
                break;
            case 'timeout':
                $this->setTimeout($value);
                break;
            case 'retry_attempts':
                $this->setRetryAttempts($value);
                break;
            case 'enabled':
                $this->setEnabled($value);
                break;
            case 'transport':
                $this->setTransport($value);
                break;
            default:
                throw new ConfigurationException("Unknown configuration key: {$key}");
        }

        return $this;
    }
} 