<?php

namespace OutboundIQ\Tests\Interceptors;

use OutboundIQ\Client;
use OutboundIQ\Interceptors\CurlInterceptor;
use PHPUnit\Framework\TestCase;

class CurlInterceptorTest extends TestCase
{
    private string $validApiKey = 'test_key_12345678901234567890123456789012';
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client($this->validApiKey);
        CurlInterceptor::register($this->client);
    }

    public function testBasicCurlRequest()
    {
        $ch = curl_init('http://httpbin.org/get');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $this->assertNotFalse($response);
        $this->assertEquals(200, $info['http_code']);
        $this->assertJson($response);
    }

    public function testCurlWithCustomHeaders()
    {
        $ch = curl_init('http://httpbin.org/headers');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-Custom-Header: TestValue',
                'User-Agent: OutboundIQ-Test'
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $this->assertNotFalse($response);
        $responseData = json_decode($response, true);
        $this->assertArrayHasKey('headers', $responseData);
        $this->assertEquals('TestValue', $responseData['headers']['X-Custom-Header']);
    }

    public function testCurlPostRequest()
    {
        $postData = ['test' => 'value'];
        $ch = curl_init('http://httpbin.org/post');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $this->assertNotFalse($response);
        $responseData = json_decode($response, true);
        $this->assertEquals(json_encode($postData), $responseData['data']);
    }

    public function testCurlError()
    {
        $ch = curl_init('http://non-existent-domain.outboundiq/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $this->assertFalse($response);
        $this->assertNotEmpty($error);
    }

    public function testCurlTimeout()
    {
        $ch = curl_init('http://httpbin.org/delay/5');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $this->assertFalse($response);
        $this->assertStringContainsString('timeout', strtolower($error));
    }
} 