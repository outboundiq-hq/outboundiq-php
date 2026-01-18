<?php

use OutboundIQ\Client;
use OutboundIQ\Exceptions\ConfigurationException;
use OutboundIQ\Interceptors\CurlInterceptor;
use OutboundIQ\Interceptors\GuzzleMiddleware;
use OutboundIQ\Interceptors\StreamWrapper;

if (!function_exists('outboundiq_init')) {
    function outboundiq_init(string $apiKey, array $options = []): Client
    {
        static $client = null;

        if ($client === null) {
            $client = new Client($apiKey, $options);
            
            CurlInterceptor::register($client);
            StreamWrapper::register($client);
            
            if (class_exists('\GuzzleHttp\Client')) {
                GuzzleMiddleware::register($client);
            }

            register_shutdown_function(function() use ($client) {
                $client->flush();
            });
        }

        return $client;
    }
}

// Auto-initialize if an API key is set in environment
if ($apiKey = getenv('OUTBOUNDIQ_KEY')) {
    outboundiq_init($apiKey);
} 