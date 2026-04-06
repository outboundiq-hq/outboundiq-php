# OutboundIQ PHP

> API Intelligence for PHP applications.

OutboundIQ helps you understand and optimize your outbound API calls in real time, not just monitor them.

Instead of guessing when a provider is down, slow, or degrading, OutboundIQ gives you visibility and decision-making power across your external dependencies.

## The problem

Your app depends on APIs.

Payments, KYC, messaging, analytics, everything.

When something breaks:

- Dashboards stay green
- Errors spike quietly
- One endpoint fails while others work
- You do not know which provider to trust

## The solution: API Intelligence

OutboundIQ tracks outbound requests and turns them into actionable insights:

- Real-time provider health
- Endpoint-level visibility (not just provider status)
- Smart routing recommendations
- Collective intelligence across your traffic

## Installation

```bash
composer require outboundiq/outboundiq-php
```

## Setup

### Option A: one-liner bootstrap (recommended)

```php
require_once 'vendor/autoload.php';

$client = outboundiq_init('your-api-key', [
    'transport' => 'async', // async | sync | queue
]);
```

### Option B: explicit client construction

```php
use OutboundIQ\Client;

$client = new Client('your-api-key', [
    'enabled' => true,
    'transport' => 'async',      // async | sync | queue
    'batch_size' => 50,
    'timeout' => 5,
    'retry_attempts' => 3,
    'url' => 'https://agent.outboundiq.dev/api/metric',
]);
```

Note: this package uses API key authentication. You do not pass `project_id` in the client constructor.

## Quick example: smart routing

```php
$decision = $client->recommend('payment-processing');

$provider = $decision['decision']['use'] ?? 'stripe';

// Use the recommended provider dynamically
```

Instead of hardcoding providers, your app adapts in real time.

## Track your API calls

OutboundIQ supports multiple transport layers:

- cURL interception (registered by `outboundiq_init`)
- `file_get_contents` interception (registered by `outboundiq_init`)
- Guzzle middleware (available after `outboundiq_init`, then attach handler stack on each Guzzle client you want tracked)

### Guzzle example

```php
use GuzzleHttp\Client as GuzzleClient;
use OutboundIQ\Interceptors\GuzzleMiddleware;

$http = new GuzzleClient([
    'handler' => GuzzleMiddleware::getHandlerStack(),
]);

$response = $http->get('https://api.example.com/status');
```

Important: Guzzle requests are tracked only for clients created with `GuzzleMiddleware::getHandlerStack()`.  
If you already have existing Guzzle clients, update their handler config to use that stack.

## API Intelligence methods

### Provider health

```php
$status = $client->providerStatus('stripe');
```

Use this to inspect provider-level health, incidents, and decision data.

### Endpoint intelligence

```php
$endpoint = $client->endpointStatus('stripe-post-v1-charges');
```

Because providers do not fail uniformly, endpoints do.

### Smart recommendations

```php
$decision = $client->recommend('kyc-verification');
```

OutboundIQ recommends which provider path to use based on live performance signals.

## Why OutboundIQ

Most tools tell you what happened.

OutboundIQ helps you decide what to do next.

- Not just logs, decisions
- Not just monitoring, intelligence
- Not just alerts, actions

## Works anywhere

Framework-agnostic:

- Vanilla PHP
- Laravel (or use `laravel-outboundiq` for framework-native integration)
- Symfony
- Custom stacks

## Configuration reference

```php
$client = new Client('your-api-key', [
    'enabled' => true,
    'transport' => 'async',   // async | sync | queue
    'batch_size' => 50,
    'buffer_size' => 100,
    'flush_interval' => 30,
    'timeout' => 5,
    'retry_attempts' => 3,
    'temp_dir' => sys_get_temp_dir(),
    'url' => 'https://agent.outboundiq.dev/api/metric',
]);
```

Environment convenience:

```bash
export OUTBOUNDIQ_KEY=your-api-key
```

When `OUTBOUNDIQ_KEY` is set, bootstrap auto-initialization can kick in.

## Use cases

- Payment provider failover
- KYC provider optimization
- API reliability monitoring
- Multi-provider routing
- Detecting silent endpoint failures

## Learn more

- Website: [https://outboundiq.dev](https://outboundiq.dev)
- Docs: [https://outboundiq.dev/docs](https://outboundiq.dev/docs)

## Contributing

Contributions, feedback, and ideas are welcome.

## Requirements

- PHP 8.2+
- ext-curl
- ext-json

## License

MIT
