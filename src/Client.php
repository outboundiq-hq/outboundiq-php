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
     * @param array|null $userContext User context (user_id, user_type, context)
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
        ?string $error_type = null,
        ?array $userContext = null
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
            error_type: $error_type,
            userContext: $userContext
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

    /**
     * Get recommendation for a service
     *
     * Returns the best provider/endpoint to use based on:
     * - Your actual API usage data (success rate, latency, stability)
     * - Provider status page health
     * - Recent incidents
     *
     * @param string $serviceName The service name (e.g., 'payment-processing')
     * @param array $options Optional settings:
     *                       - 'request_id': Your own trace ID for correlation (auto-generated if not provided)
     *                       - 'user_context': User context array (user_id, user_type, context)
     * @return array|null The recommendation response from server, or null on network failure
     */
    public function recommend(string $serviceName, array $options = []): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $url = $this->config->getBaseUrl() . '/v1/recommend/' . urlencode($serviceName);
            
            // Generate or use provided request ID for tracing
            $requestId = $options['request_id'] ?? $this->generateUuid();
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->config->getApiKey(),
                'Accept' => 'application/json',
                'X-Request-Id' => $requestId,
            ];
            
            // Add user context as header if provided
            if (!empty($options['user_context'])) {
                $headers['X-User-Context'] = json_encode($options['user_context']);
            }
            
            $response = $this->httpClient->get($url, [
                'headers' => $headers,
                'timeout' => $this->config->getTimeout(),
                'http_errors' => false, // Don't throw exceptions for 4xx/5xx
            ]);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);
            
            // Return the response regardless of status code - server always returns valid JSON
            // Status codes: 200 (success), 401 (auth error), 404 (not found)
            return $decoded;
        } catch (GuzzleException $e) {
            // Network failure - return null, don't break user's application
            error_log('[OutboundIQ] Recommendation request failed: ' . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log('[OutboundIQ] Unexpected error in recommend(): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get status and metrics for a provider
     *
     * Returns real-time actionable data for decision-making:
     * - Provider status (from status page)
     * - Aggregate metrics (success rate, latency)
     * - Active incidents
     * - Affected components
     *
     * @param string $providerSlug The provider slug (e.g., 'paystack')
     * @param array $options Optional settings:
     *                       - 'user_context': User context array (user_id, user_type, context)
     * @return array|null The status response from server, or null on network failure
     */
    public function providerStatus(string $providerSlug, array $options = []): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $url = $this->config->getBaseUrl() . '/v1/provider/' . urlencode($providerSlug) . '/status';
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->config->getApiKey(),
                'Accept' => 'application/json',
            ];
            
            // Add user context as header if provided
            if (!empty($options['user_context'])) {
                $headers['X-User-Context'] = json_encode($options['user_context']);
            }
            
            $response = $this->httpClient->get($url, [
                'headers' => $headers,
                'timeout' => $this->config->getTimeout(),
                'http_errors' => false,
            ]);

            $body = $response->getBody()->getContents();
            return json_decode($body, true);
        } catch (GuzzleException $e) {
            error_log('[OutboundIQ] Provider status request failed: ' . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log('[OutboundIQ] Unexpected error in providerStatus(): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get status and metrics for a specific endpoint
     *
     * Returns real-time actionable data for decision-making:
     * - Endpoint-specific metrics (success rate, latency, schema stability)
     * - Provider status (from status page)
     * - Active incidents
     * - Latency trend
     *
     * @param string $endpointSlug The endpoint slug (e.g., 'paystack-post-transaction-initialize')
     * @param array $options Optional settings:
     *                       - 'user_context': User context array (user_id, user_type, context)
     * @return array|null The status response from server, or null on network failure
     */
    public function endpointStatus(string $endpointSlug, array $options = []): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $url = $this->config->getBaseUrl() . '/v1/endpoint/' . urlencode($endpointSlug) . '/status';
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->config->getApiKey(),
                'Accept' => 'application/json',
            ];
            
            // Add user context as header if provided
            if (!empty($options['user_context'])) {
                $headers['X-User-Context'] = json_encode($options['user_context']);
            }
            
            $response = $this->httpClient->get($url, [
                'headers' => $headers,
                'timeout' => $this->config->getTimeout(),
                'http_errors' => false,
            ]);

            $body = $response->getBody()->getContents();
            return json_decode($body, true);
        } catch (GuzzleException $e) {
            error_log('[OutboundIQ] Endpoint status request failed: ' . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log('[OutboundIQ] Unexpected error in endpointStatus(): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate a UUID v4
     * 
     * @return string
     */
    private function generateUuid(): string
    {
        // Use random_bytes if available (PHP 7+)
        $data = random_bytes(16);
        
        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
} 