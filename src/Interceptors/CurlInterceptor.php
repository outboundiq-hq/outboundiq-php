<?php

namespace OutboundIQ\Interceptors;

use OutboundIQ\Client;

class CurlInterceptor
{
    /**
     * @var Client
     */
    private static Client $client;

    /**
     * @var array
     */
    private static array $handles = [];

    private static array $originalFunctions = [];

    /**
     * Register the interceptor
     *
     * @param Client $client
     * @return void
     */
    public static function register(Client $client): void
    {
        self::$client = $client;
        
        // Store original functions
        self::$originalFunctions = [
            'curl_init' => function_exists('curl_init_original') ? 'curl_init_original' : 'curl_init',
            'curl_exec' => function_exists('curl_exec_original') ? 'curl_exec_original' : 'curl_exec',
            'curl_setopt' => function_exists('curl_setopt_original') ? 'curl_setopt_original' : 'curl_setopt',
            'curl_setopt_array' => function_exists('curl_setopt_array_original') ? 'curl_setopt_array_original' : 'curl_setopt_array',
            'curl_close' => function_exists('curl_close_original') ? 'curl_close_original' : 'curl_close'
        ];
    }

    /**
     * Proxy for curl_init
     */
    public static function init($url = null)
    {
        $handle = call_user_func(self::$originalFunctions['curl_init'], $url);
        if ($handle !== false) {
            self::$handles[(int)$handle] = [
                'url' => $url,
                'start_time' => microtime(true),
                'options' => []
            ];
        }
        return $handle;
    }

    /**
     * Proxy for curl_exec
     */
    public static function exec($ch)
    {
        $handle = (int)$ch;
        $info = self::$handles[$handle] ?? null;
        
        if ($info) {
            $startTime = $info['start_time'];
            $response = call_user_func(self::$originalFunctions['curl_exec'], $ch);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $curlInfo = curl_getinfo($ch);
            
            // Determine the HTTP method
            $method = 'GET';
            if (isset($info['options'][CURLOPT_CUSTOMREQUEST])) {
                $method = $info['options'][CURLOPT_CUSTOMREQUEST];
            } elseif (isset($info['options'][CURLOPT_POST]) && $info['options'][CURLOPT_POST]) {
                $method = 'POST';
            } elseif (isset($info['options'][CURLOPT_HTTPGET]) && $info['options'][CURLOPT_HTTPGET]) {
                $method = 'GET';
            } elseif (isset($info['options'][CURLOPT_NOBODY]) && $info['options'][CURLOPT_NOBODY]) {
                $method = 'HEAD';
            }

            $statusCode = $curlInfo['http_code'] ?? 0;
            $errorMessage = null;
            $errorType = null;

            if ($response === false) {
                $errorMessage = curl_error($ch);
                $errorCode = curl_errno($ch);

                // Map curl error codes to error types
                switch ($errorCode) {
                    case CURLE_OPERATION_TIMEOUTED:
                        $errorType = 'timeout';
                        break;
                    case CURLE_COULDNT_CONNECT:
                        $errorType = 'connection_error';
                        break;
                    case CURLE_COULDNT_RESOLVE_HOST:
                        $errorType = 'dns_error';
                        break;
                    case CURLE_SSL_CONNECT_ERROR:
                        $errorType = 'ssl_error';
                        break;
                    default:
                        $errorType = 'curl_error';
                }
            } elseif ($statusCode >= 400) {
                $errorType = 'http_error';
                $errorMessage = "HTTP $statusCode error";
            }
            
            self::$client->trackApiCall(
                url: $curlInfo['url'] ?? $info['url'],
                method: $method,
                duration: $duration,
                statusCode: $statusCode,
                requestHeaders: $info['options'][CURLOPT_HTTPHEADER] ?? [],
                requestBody: $info['options'][CURLOPT_POSTFIELDS] ?? null,
                responseHeaders: [],
                responseBody: $response,
                request_type: 'curl',
                error_message: $errorMessage,
                error_type: $errorType
            );
            
            return $response;
        }
        
        return call_user_func(self::$originalFunctions['curl_exec'], $ch);
    }

    /**
     * Proxy for curl_setopt
     */
    public static function setopt($ch, $option, $value)
    {
        $handle = (int)$ch;
        if (isset(self::$handles[$handle])) {
            self::$handles[$handle]['options'][$option] = $value;
        }
        return call_user_func(self::$originalFunctions['curl_setopt'], $ch, $option, $value);
    }

    /**
     * Proxy for curl_setopt_array
     */
    public static function setopt_array($ch, array $options)
    {
        $handle = (int)$ch;
        if (isset(self::$handles[$handle])) {
            self::$handles[$handle]['options'] = array_merge(
                self::$handles[$handle]['options'],
                $options
            );
        }
        return call_user_func(self::$originalFunctions['curl_setopt_array'], $ch, $options);
    }

    /**
     * Proxy for curl_close
     */
    public static function close($ch)
    {
        $handle = (int)$ch;
        if (isset(self::$handles[$handle])) {
            unset(self::$handles[$handle]);
        }
        return call_user_func(self::$originalFunctions['curl_close'], $ch);
    }

    /**
     * Get the tracking instance for a handle
     */
    public static function getTracking($ch): ?array
    {
        return self::$handles[(int)$ch] ?? null;
    }
} 