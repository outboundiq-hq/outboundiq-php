<?php

use OutboundIQ\Client;
use OutboundIQ\Exceptions\ConfigurationException;
use OutboundIQ\Interceptors\CurlInterceptor;
use OutboundIQ\Interceptors\GuzzleMiddleware;
use OutboundIQ\Interceptors\StreamWrapper;

if (!function_exists('outboundiq_init')) {
    /**
     * Initialize OutboundIQ monitoring
     *
     * @param string $apiKey Your OutboundIQ API key
     * @param array $options Configuration options
     * @return Client
     * @throws ConfigurationException
     */
    function outboundiq_init(string $apiKey, array $options = []): Client
    {
        static $client = null;

        if ($client === null) {
            $client = new Client($apiKey, $options);
            
            // Register interceptors
            CurlInterceptor::register($client);
            StreamWrapper::register($client);
            
            // Register Guzzle middleware if Guzzle is available
            if (class_exists('\GuzzleHttp\Client')) {
                GuzzleMiddleware::register($client);
            }

            // Register shutdown function to ensure metrics are sent
            register_shutdown_function(function() use ($client) {
                $client->flush();
            });
        }

        return $client;
    }
}

// Auto-initialize if an API key is set in environment
if ($apiKey = getenv('OUTBOUNDIQ_API_KEY')) {
    outboundiq_init($apiKey);
} 