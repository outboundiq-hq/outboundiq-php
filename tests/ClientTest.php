<?php

namespace OutboundIQ\Tests;

use OutboundIQ\Client;
use OutboundIQ\Configuration;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private string $validApiKey = 'test_key_12345678901234567890123456789012';
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client($this->validApiKey);
    }

    public function testClientInitialization()
    {
        $this->assertInstanceOf(Configuration::class, $this->client->getConfig());
        $this->assertEquals($this->validApiKey, $this->client->getConfig()->getApiKey());
    }

    public function testTrackApiCall()
    {
        // Since we're using background processing, we'll test the method doesn't throw exceptions
        $this->client->trackApiCall(
            url: 'https://api.example.com/test',
            method: 'GET',
            duration: 150.5,
            statusCode: 200,
            requestHeaders: ['Accept' => 'application/json'],
            requestBody: '{"test": true}',
            responseHeaders: ['Content-Type' => 'application/json'],
            responseBody: '{"status": "success"}'
        );

        $this->assertTrue(true); // If we got here without exceptions, test passes
    }

    public function testTrackApiCallWithDisabledMonitoring()
    {
        $client = new Client($this->validApiKey, ['enabled' => false]);
        
        $client->trackApiCall(
            url: 'https://api.example.com/test',
            method: 'GET',
            duration: 150.5,
            statusCode: 200
        );

        $this->assertTrue(true); // If we got here without exceptions, test passes
    }

    public function testExcludeOutboundIQCalls()
    {
        $client = new Client($this->validApiKey);
        
        $client->trackApiCall(
            url: 'https://api.outboundiq.com/metrics',
            method: 'POST',
            duration: 100,
            statusCode: 200
        );

        $this->assertTrue(true); // If we got here without exceptions, test passes
    }

    public function testExcludeLocalhost()
    {
        $client = new Client($this->validApiKey);
        
        $client->trackApiCall(
            url: 'http://localhost:8000/api',
            method: 'GET',
            duration: 100,
            statusCode: 200
        );

        $client->trackApiCall(
            url: 'http://127.0.0.1/api',
            method: 'GET',
            duration: 100,
            statusCode: 200
        );

        $this->assertTrue(true); // If we got here without exceptions, test passes
    }

    public function testFlush()
    {
        // Add some metrics
        $this->client->trackApiCall(
            url: 'https://api.example.com/test1',
            method: 'GET',
            duration: 100,
            statusCode: 200
        );

        $this->client->trackApiCall(
            url: 'https://api.example.com/test2',
            method: 'POST',
            duration: 200,
            statusCode: 201
        );

        // Flush should not throw any exceptions
        $this->client->flush();
        $this->assertTrue(true);
    }
} 