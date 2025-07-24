# OutboundIQ PHP Package

OutboundIQ is a powerful API monitoring tool that automatically tracks and analyzes your third-party API calls. It provides real-time insights into API performance, reliability, and behavior across your entire application.

## Features

- 🔄 **Automatic API Call Tracking**: Monitors all HTTP requests without requiring manual instrumentation
- 🚀 **Zero Performance Impact**: Non-blocking background processing with async transport
- 🔍 **Comprehensive Metrics**: Captures duration, status codes, headers, payloads, and errors
- 🛠 **Multiple HTTP Client Support**: Works with cURL, Guzzle, and file_get_contents()
- 🎯 **Smart Error Detection**: Automatically categorizes errors (timeout, DNS, SSL, etc.)
- 📊 **Performance Analytics**: Track response times, memory usage, and resource consumption
- 🔒 **Secure by Design**: Proper API key handling and secure data transmission
- 🔄 **Retry Mechanism**: Built-in retry logic for failed metric submissions
- 💾 **Efficient Storage**: Smart buffering and batch processing of metrics
- 🧹 **Auto-Cleanup**: Proper management of temporary files and resources

## Installation

Install the package via Composer:

```bash
composer require outboundiq/outboundiq-php
```

## Quick Start

1. Get your API key from [OutboundIQ Dashboard](https://dashboard.outboundiq.com)

2. Initialize the package:

```php
require_once 'vendor/autoload.php';

outboundiq_init('your-api-key-here');
```

That's it! OutboundIQ will automatically start monitoring all your API calls.

## Configuration Options

```php
use OutboundIQ\Client;

$client = outboundiq_init('your-api-key-here', [
    // Basic Settings
    'enabled' => true,              // Enable/disable monitoring
    'endpoint' => 'https://api.outboundiq.com/v1/metrics',  // API endpoint
    'version' => '1.0.0',           // Package version

    // Performance Tuning
    'batch_size' => 50,             // Number of metrics to batch before sending
    'max_payload_size' => 65536,    // Maximum size of payload in bytes (64KB)
    'buffer_size' => 100,           // Number of metrics to buffer
    'max_concurrent_requests' => 10, // Maximum concurrent metric submissions

    // Timing Settings
    'timeout' => 5,                 // Timeout for sending metrics (seconds)
    'retry_attempts' => 3,          // Number of retry attempts
    'flush_interval' => 60,         // Force flush interval (seconds)

    // Storage Settings
    'temp_dir' => '/tmp',           // Directory for temporary files
    'transport' => 'file'           // Transport method ('file' or 'async')
]);
```

## HTTP Client Support

OutboundIQ automatically intercepts and monitors calls from:

### cURL
```php
// Direct curl usage
$ch = curl_init('https://api.example.com/endpoint');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
```

### Guzzle
```php
// Using Guzzle with middleware
$client = new GuzzleHttp\Client([
    'handler' => GuzzleMiddleware::getHandlerStack()
]);
$response = $client->get('https://api.example.com/endpoint');
```

### file_get_contents()
```php
// Using file_get_contents
$response = file_get_contents('https://api.example.com/endpoint');
```

## Error Handling

OutboundIQ automatically detects and categorizes various types of errors:

- Network Errors
  - Connection timeouts
  - DNS resolution failures
  - SSL/TLS errors
  - Connection refused

- HTTP Errors
  - 4xx Client errors
  - 5xx Server errors
  - Invalid responses

- Performance Issues
  - Slow responses
  - Memory usage spikes
  - Resource exhaustion

## Best Practices

1. **Configuration**
   - Set appropriate batch sizes for your traffic volume
   - Configure retry attempts based on your reliability needs
   - Adjust flush intervals based on your real-time requirements

2. **Performance**
   - Use async transport for high-traffic applications
   - Set reasonable buffer sizes to manage memory usage
   - Configure appropriate timeouts for your use case

3. **Monitoring**
   - Monitor the monitoring! Set up alerts for metric submission failures
   - Regularly check the dashboard for API performance trends
   - Use the insights to optimize your API integrations

## Testing

Run the test suite:

```bash
composer test
```

The test suite includes:
- Unit tests for core functionality
- Integration tests for HTTP clients
- Error handling tests
- Performance tests

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@outboundiq.com instead of using the issue tracker.

## License

The OutboundIQ PHP Package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Support

- [Documentation](https://docs.outboundiq.com)
- [Community Forum](https://community.outboundiq.com)
- [Email Support](mailto:support@outboundiq.com) 