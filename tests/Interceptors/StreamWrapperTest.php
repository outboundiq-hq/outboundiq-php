<?php

namespace OutboundIQ\Tests\Interceptors;

use OutboundIQ\Client;
use OutboundIQ\Interceptors\StreamWrapper;
use PHPUnit\Framework\TestCase;

class StreamWrapperTest extends TestCase
{
    private string $validApiKey = 'test_key_12345678901234567890123456789012';
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client($this->validApiKey);
        StreamWrapper::register($this->client);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Re-register the original wrappers
        stream_wrapper_restore('http');
        stream_wrapper_restore('https');
    }

    public function testFileGetContentsTracking()
    {
        // Using httpbin.org for testing as it's a reliable test endpoint
        $response = @file_get_contents('http://httpbin.org/get');
        
        $this->assertNotFalse($response);
        $this->assertJson($response);
    }

    public function testFileGetContentsWithHeaders()
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: OutboundIQ-Test'
                ]
            ]
        ];

        $context = stream_context_create($opts);
        $response = @file_get_contents('http://httpbin.org/get', false, $context);
        
        $this->assertNotFalse($response);
        $this->assertJson($response);
    }

    public function testFileGetContentsError()
    {
        // Test with non-existent domain
        $response = @file_get_contents('http://non-existent-domain.outboundiq/');
        
        $this->assertFalse($response);
    }

    public function testLocalFileNotIntercepted()
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'outboundiq_test_');
        file_put_contents($tempFile, 'test content');

        // This should not be intercepted
        $content = file_get_contents($tempFile);
        
        $this->assertEquals('test content', $content);
        
        // Clean up
        unlink($tempFile);
    }
} 