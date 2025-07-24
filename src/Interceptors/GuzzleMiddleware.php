<?php

namespace OutboundIQ\Interceptors;

use GuzzleHttp\HandlerStack;
use OutboundIQ\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleMiddleware
{
    private static Client $client;
    private static ?HandlerStack $defaultStack = null;

    public static function register(Client $client): void
    {
        error_log('[OutboundIQ] Registering GuzzleMiddleware');
        self::$client = $client;

        // Create a new handler stack with our middleware
        $stack = HandlerStack::create();
        $stack->push(self::createMiddleware(), 'outboundiq');
        
        // Store the stack for use in new client instances
        self::$defaultStack = $stack;
        error_log('[OutboundIQ] Handler stack created and stored');
    }

    /**
     * Get the handler stack with OutboundIQ middleware
     */
    public static function getHandlerStack(): HandlerStack
    {
        if (self::$defaultStack === null) {
            throw new \RuntimeException('GuzzleMiddleware has not been registered');
        }
        error_log('[OutboundIQ] Returning handler stack for new client');
        return self::$defaultStack;
    }

    private static function createMiddleware(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                error_log('[OutboundIQ] Intercepting request to: ' . $request->getUri());
                $startTime = microtime(true);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $startTime) {
                        $duration = (microtime(true) - $startTime) * 1000;
                        error_log('[OutboundIQ] Request completed in ' . $duration . 'ms');

                        // Extract request body
                        $requestBody = null;
                        if ($request->getBody()->isSeekable()) {
                            $requestBody = $request->getBody()->getContents();
                            $request->getBody()->rewind();
                        }

                        // Extract response body
                        $responseBody = null;
                        if ($response->getBody()->isSeekable()) {
                            $responseBody = $response->getBody()->getContents();
                            $response->getBody()->rewind();
                        }

                        // Track the API call
                        self::$client->trackApiCall(
                            url: (string)$request->getUri(),
                            method: $request->getMethod(),
                            duration: $duration,
                            statusCode: $response->getStatusCode(),
                            requestHeaders: $request->getHeaders(),
                            requestBody: $requestBody,
                            responseHeaders: $response->getHeaders(),
                            responseBody: $responseBody,
                            request_type: 'guzzle'
                        );

                        error_log('[OutboundIQ] API call tracked successfully');
                        return $response;
                    },
                    function ($reason) use ($request, $startTime) {
                        $duration = (microtime(true) - $startTime) * 1000;
                        error_log('[OutboundIQ] Request failed after ' . $duration . 'ms');

                        // Extract request body
                        $requestBody = null;
                        if ($request->getBody()->isSeekable()) {
                            $requestBody = $request->getBody()->getContents();
                            $request->getBody()->rewind();
                        }

                        // Determine error type and status code
                        $statusCode = 0;
                        $errorType = 'unknown_error';
                        $errorMessage = $reason->getMessage();

                        if ($reason instanceof \GuzzleHttp\Exception\ConnectException) {
                            $errorType = 'connection_error';
                            if (strpos($errorMessage, 'timed out') !== false) {
                                $errorType = 'timeout';
                            } elseif (strpos($errorMessage, 'Could not resolve') !== false) {
                                $errorType = 'dns_error';
                            }
                        } elseif ($reason instanceof \GuzzleHttp\Exception\RequestException) {
                            if ($reason->hasResponse()) {
                                $statusCode = $reason->getResponse()->getStatusCode();
                                $errorType = 'http_error';
                            }
                        }

                        // Track the failed API call
                        self::$client->trackApiCall(
                            url: (string)$request->getUri(),
                            method: $request->getMethod(),
                            duration: $duration,
                            statusCode: $statusCode,
                            requestHeaders: $request->getHeaders(),
                            requestBody: $requestBody,
                            responseHeaders: [],
                            responseBody: null,
                            request_type: 'guzzle',
                            error_message: $errorMessage,
                            error_type: $errorType
                        );

                        error_log('[OutboundIQ] Failed API call tracked');
                        throw $reason;
                    }
                );
            };
        };
    }
} 