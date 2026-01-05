<?php

namespace OutboundIQ;

use GuzzleHttp\Exception\GuzzleException;
use OutboundIQ\Models\ApiCall;
use OutboundIQ\Transports\AsyncTransport;
use OutboundIQ\Transports\SyncTransport;
use OutboundIQ\Transports\QueueTransport;
use OutboundIQ\Transports\TransportInterface;
use GuzzleHttp\Client as GuzzleClient;
use Exception;

class Client
{
    private Configuration $config;
    private TransportInterface $transport;
    private bool $enabled = true;
    private GuzzleClient $httpClient;

    public function __construct(?string $apiKey = null, array $options = [])
    {
        $this->enabled = $apiKey !== null && ($options['enabled'] ?? true);
        
        if (!$this->enabled) {
            return;
        }

        $this->config = new Configuration($apiKey, $options);
        $this->transport = $this->createTransport();
        $this->httpClient = new GuzzleClient();
        
        register_shutdown_function([$this->transport, 'flush']);
    }

    private function createTransport(): TransportInterface
    {
        return match ($this->config->getTransport()) {
            'sync' => new SyncTransport($this->config),
            'queue' => new QueueTransport($this->config),
            default => new AsyncTransport($this->config),
        };
    }

    private function shouldExcludeUrl(string $url): bool
    {
        return $url === $this->config->getEndpoint() ||
               str_contains($url, 'localhost') || 
               str_contains($url, '127.0.0.1');
    }

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
        if (!$this->enabled || $this->shouldExcludeUrl($url)) {
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
    }

    public function flush(): void
    {
        if ($this->enabled) {
            $this->transport->flush();
        }
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

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
            return [];
        }
    }

    public function enable(): void
    {
        $this->config->setEnabled(true);
    }

    public function disable(): void
    {
        $this->config->setEnabled(false);
    }

    /**
     * Get recommendation for which provider/endpoint to use.
     */
    public function recommend(string $serviceName, array $options = []): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $url = $this->config->getBaseUrl() . '/v1/recommend/' . urlencode($serviceName);
            $requestId = $options['request_id'] ?? $this->generateUuid();
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->config->getApiKey(),
                'Accept' => 'application/json',
                'X-Request-Id' => $requestId,
            ];
            
            if (!empty($options['user_context'])) {
                $headers['X-User-Context'] = json_encode($options['user_context']);
            }
            
            $response = $this->httpClient->get($url, [
                'headers' => $headers,
                'timeout' => $this->config->getTimeout(),
                'http_errors' => false,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get status and metrics for a provider.
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
            
            if (!empty($options['user_context'])) {
                $headers['X-User-Context'] = json_encode($options['user_context']);
            }
            
            $response = $this->httpClient->get($url, [
                'headers' => $headers,
                'timeout' => $this->config->getTimeout(),
                'http_errors' => false,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get status and metrics for a specific endpoint.
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
            
            if (!empty($options['user_context'])) {
                $headers['X-User-Context'] = json_encode($options['user_context']);
            }
            
            $response = $this->httpClient->get($url, [
                'headers' => $headers,
                'timeout' => $this->config->getTimeout(),
                'http_errors' => false,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            return null;
        }
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
