<?php

namespace OutboundIQ\Models;

use OutboundIQ\Contracts\MetricInterface;

class ApiCall implements MetricInterface
{
    private string $url;
    private string $method;
    private float $duration;
    private int $statusCode;
    private array $requestHeaders = [];
    private ?string $requestBody = null;
    private array $responseHeaders = [];
    private ?string $responseBody = null;
    private string $transactionId;
    private float $timestamp;
    private ?string $request_type = null;
    private ?array $error = null;
    private ?array $userContext = null;

    public function __construct(
        string $url,
        string $method,
        float $duration,
        int $statusCode,
        array $requestHeaders = [],
        ?string $requestBody = null,
        array $responseHeaders = [],
        ?string $responseBody = null,
        ?string $request_type = null,
        ?string $error_message = null,
        ?string $error_type = null,
        ?array $userContext = null
    ) {
        $this->url = $url;
        $this->method = $method;
        $this->duration = $duration;
        $this->statusCode = $statusCode;
        $this->requestHeaders = $requestHeaders;
        $this->requestBody = $requestBody;
        $this->responseHeaders = $responseHeaders;
        $this->responseBody = $responseBody;
        $this->timestamp = microtime(true);
        $this->transactionId = $this->generateTransactionId();
        $this->request_type = $request_type;
        $this->userContext = $userContext;
        
        if ($error_message !== null || $error_type !== null) {
            $this->error = [
                'message' => $error_message,
                'type' => $error_type
            ];
        }
    }

    private function generateTransactionId(): string
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(16));
        }
        
        return uniqid('', true);
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        $data = [
            'transaction_id' => $this->transactionId,
            'url' => $this->url,
            'method' => $this->method,
            'duration' => $this->duration,
            'status_code' => $this->statusCode,
            'request_headers' => $this->requestHeaders,
            'request_body' => $this->requestBody,
            'response_headers' => $this->responseHeaders,
            'response_body' => $this->responseBody,
            'timestamp' => $this->timestamp,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'request_type' => $this->request_type,
        ];

        if ($this->error !== null) {
            $data['error'] = $this->error;
        }
        
        if ($this->userContext !== null) {
            $data['user_context'] = $this->userContext;
        }

        return $data;
    }
} 