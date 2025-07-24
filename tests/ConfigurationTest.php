<?php

namespace OutboundIQ\Tests;

use OutboundIQ\Configuration;
use OutboundIQ\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    private string $validApiKey = 'test_key_12345678901234567890123456789012';

    public function testValidConfiguration()
    {
        $config = new Configuration($this->validApiKey);
        
        $this->assertEquals($this->validApiKey, $config->getApiKey());
        $this->assertEquals('https://api.outboundiq.com/v1/metrics', $config->getEndpoint());
        $this->assertEquals(50, $config->getBatchSize());
        $this->assertTrue($config->isEnabled());
        $this->assertEquals(5, $config->getTimeout());
        $this->assertEquals(3, $config->getRetryAttempts());
    }

    public function testCustomConfiguration()
    {
        $options = [
            'endpoint' => 'https://custom.api.com',
            'batch_size' => 100,
            'enabled' => false,
            'timeout' => 10,
            'retry_attempts' => 5
        ];

        $config = new Configuration($this->validApiKey, $options);
        
        $this->assertEquals('https://custom.api.com', $config->getEndpoint());
        $this->assertEquals(100, $config->getBatchSize());
        $this->assertFalse($config->isEnabled());
        $this->assertEquals(10, $config->getTimeout());
        $this->assertEquals(5, $config->getRetryAttempts());
    }

    public function testEmptyApiKeyThrowsException()
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('API key cannot be empty');
        
        new Configuration('');
    }

    public function testInvalidApiKeyThrowsException()
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid API key format');
        
        new Configuration('short_key');
    }
} 