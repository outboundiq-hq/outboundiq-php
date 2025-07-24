<?php

namespace OutboundIQ;

use GuzzleHttp\Exception\GuzzleException;
use OutboundIQ\Contracts\MetricInterface;
use OutboundIQ\Models\ApiCall;
use OutboundIQ\Transports\AsyncTransport;
use OutboundIQ\Transports\TransportInterface;
use OutboundIQ\Interceptors\GuzzleMiddleware;
use OutboundIQ\Interceptors\StreamWrapper;
use OutboundIQ\Interceptors\CurlInterceptor;
use GuzzleHttp\Client as GuzzleClient;
use OutboundIQ\Exceptions\ConfigurationException;
use Exception;
use Random\RandomException;

class Client
{
    /**
     * @var Configuration
     */
    private Configuration $config;

    /**
     * @var AsyncTransport
     */
    private AsyncTransport $transport;

    /**
     * @var bool
     */
    private bool $enabled = true;

    /**
     * @var GuzzleClient
     */
    private GuzzleClient $httpClient;

    /**
     * Initialize OutboundIQ client
     *
     * @param string|null $apiKey Your OutboundIQ API key
     * @param array $options Configuration options
     * @throws ConfigurationException
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        error_log('[OutboundIQ] Initializing client with options: ' . json_encode($options));
        $this->enabled = $apiKey !== null && ($options['enabled'] ?? true);
        
        if (!$this->enabled) {
            error_log('[OutboundIQ] Client disabled - no API key provided or explicitly disabled');
            return;
        }

        $this->config = new Configuration($apiKey, $options);
        $this->transport = new AsyncTransport($this->config);
        $this->httpClient = new GuzzleClient();
        error_log('[OutboundIQ] Client initialized successfully');
        
        // Register shutdown function to automatically flush remaining metrics
        register_shutdown_function([$this->transport, 'flush']);
    }


    /**
     * Check if URL should be excluded from tracking
     *
     * @param string $url
     * @return bool
     */
    private function shouldExcludeUrl(string $url): bool
    {
        // Don't track calls to OutboundIQ's own API
        return $url === $this->config->getEndpoint() ||
               str_contains($url, 'localhost') || 
               str_contains($url, '127.0.0.1');
    }

    /**
     * Track an API call
     *
     * @param string $url The API endpoint URL
     * @param string $method HTTP method used
     * @param float $duration Request duration in milliseconds
     * @param int $statusCode HTTP status code
     * @param array $requestHeaders Request headers
     * @param string|null $requestBody Request body
     * @param array $responseHeaders Response headers
     * @param string|null $responseBody Response body
     * @param string $request_type Request type
     * @param string|null $error_message Error message
     * @param string|null $error_type Error type
     * @return void
     */
    public function trackApiCall(
        string $url,
        string $method,
        float $duration,
        int $statusCode,
        array $requestHeaders = [],
        ?string $requestBody = null,
        array $responseHeaders = [],
        ?string $responseBody = null,
        string $request_type = 'unknown',
        ?string $error_message = null,
        ?string $error_type = null
    ): void {
        error_log("[OutboundIQ] Attempting to track API call to: $url");
        
        if (!$this->enabled) {
            error_log('[OutboundIQ] Tracking skipped - client is disabled');
            return;
        }

        if ($this->shouldExcludeUrl($url)) {
            error_log('[OutboundIQ] Tracking skipped - URL is excluded');
            return;
        }

        $metric = new ApiCall(
            url: $url,
            method: $method,
            duration: $duration,
            statusCode: $statusCode,
            requestHeaders: $requestHeaders,
            requestBody: $requestBody,
            responseHeaders: $responseHeaders,
            responseBody: $responseBody,
            request_type: $request_type,
            error_message: $error_message,
            error_type: $error_type
        );

        $this->transport->addMetric($metric);
        error_log('[OutboundIQ] API call tracked and added to transport');
    }

    /**
     * Flush metrics to OutboundIQ server
     *
     * @return void
     */
    public function flush(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->transport->flush();
    }

    /**
     * Get the configuration instance
     *
     * @return Configuration
     */
    public function getConfig(): Configuration
    {
        return $this->config;
    }


    /**
     * @param array $data
     * @return array
     * @throws GuzzleException
     */
    public function track(array $data): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }

        try {
            $response = $this->httpClient->post($this->config->getEndpoint(), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->getApiKey(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $data
            ]);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (Exception $e) {
            // Log the error or handle it as needed
            return [];
        }
    }

    /**
     * Enable the client
     */
    public function enable(): void
    {
        $this->config->setEnabled(true);
    }

    /**
     * Disable the client
     */
    public function disable(): void
    {
        $this->config->setEnabled(false);
    }
} 