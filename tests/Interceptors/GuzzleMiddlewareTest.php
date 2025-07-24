<?php

namespace OutboundIQ\Tests\Interceptors;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use OutboundIQ\Client;
use OutboundIQ\Interceptors\GuzzleMiddleware;
use PHPUnit\Framework\TestCase;

class GuzzleMiddlewareTest extends TestCase
{
    private string $validApiKey = 'test_key_12345678901234567890123456789012';
    private Client $client;
    private GuzzleClient $guzzle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client($this->validApiKey);
        GuzzleMiddleware::register($this->client);
        $this->guzzle = new GuzzleClient(['handler' => GuzzleMiddleware::getHandlerStack()]);
    }

    public function testSuccessfulRequest()
    {
        $response = $this->guzzle->get('http://httpbin.org/get');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getBody()->getContents());
    }

    public function testRequestWithHeaders()
    {
        $response = $this->guzzle->get('http://httpbin.org/headers', [
            'headers' => [
                'X-Custom-Header' => 'TestValue',
                'User-Agent' => 'OutboundIQ-Test'
            ]
        ]);
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('headers', $data);
        $this->assertEquals('TestValue', $data['headers']['X-Custom-Header']);
    }

    public function testPostRequest()
    {
        $postData = ['test' => 'value'];
        $response = $this->guzzle->post('http://httpbin.org/post', [
            'json' => $postData
        ]);
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals($postData, $data['json']);
    }

    public function testNotFoundError()
    {
        $this->expectException(RequestException::class);
        $this->guzzle->get('http://httpbin.org/status/404');
    }

    public function testTimeout()
    {
        $this->expectException(ConnectException::class);
        
        $client = new GuzzleClient([
            'handler' => GuzzleMiddleware::getHandlerStack(),
            'timeout' => 0.001 // 1ms timeout
        ]);
        
        $client->get('http://httpbin.org/delay/2');
    }

    public function testConnectionError()
    {
        $this->expectException(ConnectException::class);
        $this->guzzle->get('http://non-existent-domain.outboundiq/');
    }

    public function testServerError()
    {
        $this->expectException(RequestException::class);
        $this->guzzle->get('http://httpbin.org/status/500');
    }
} 