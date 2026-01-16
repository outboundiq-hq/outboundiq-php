# OutboundIQ PHP

Track and monitor your outgoing API calls with zero effort.

## Installation

```bash
composer require outboundiq/outboundiq-php
```

## Quick Start

1. Get your API key from [OutboundIQ Dashboard](https://dashboard.outboundiq.com)

2. Initialize the package:

```php
require_once 'vendor/autoload.php';

outboundiq_init('your-api-key');
```

That's it. OutboundIQ will automatically track all HTTP requests made via cURL, Guzzle, or `file_get_contents()`.

## Configuration

```php
$client = outboundiq_init('your-api-key', [
    'enabled' => true,           // Toggle monitoring on/off
    'batch_size' => 50,          // Metrics to batch before sending
    'timeout' => 5,              // Request timeout in seconds
    'retry_attempts' => 3,       // Retry failed submissions
    'transport' => 'async',      // 'async', 'sync', or 'queue'
]);
```

## Transport Options

| Transport | Best For | How It Works |
|-----------|----------|--------------|
| `async` | Traditional servers (Forge, DigitalOcean) | Background curl process |
| `sync` | Serverless (Vapor, Lambda) | Blocking curl request |
| `queue` | Laravel apps | Dispatches to queue job |

## Using with Guzzle

```php
use OutboundIQ\Interceptors\GuzzleMiddleware;

$client = new GuzzleHttp\Client([
    'handler' => GuzzleMiddleware::getHandlerStack()
]);

$response = $client->get('https://api.stripe.com/v1/charges');
```

## SDK Methods

### Check Provider Status

```php
$status = $client->providerStatus('stripe');
// Returns health status, active incidents, recommendations
```

### Get Recommendations

```php
$recommendation = $client->recommend('payment-service');
// Returns which provider to use based on current health
```

### Check Endpoint Status

```php
$status = $client->endpointStatus('stripe-charges');
// Returns metrics for a specific endpoint
```

## Environment Variable

Set `OUTBOUNDIQ_API_KEY` in your environment and the package will auto-initialize:

```bash
export OUTBOUNDIQ_API_KEY=your-api-key
```

## Requirements

- PHP 8.2+
- ext-curl
- ext-json

## License

MIT
