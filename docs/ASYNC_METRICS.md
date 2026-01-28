# Asynchronous Metrics Storage

The bundle supports asynchronous storage of performance metrics using Symfony Messenger. This allows metrics to be stored in the background without blocking the HTTP response.

## Requirements

To use asynchronous mode, you need to install Symfony Messenger:

```bash
composer require symfony/messenger
```

## Configuration

### 1. Enable asynchronous mode

In your configuration file (`config/packages/nowo_performance.yaml`):

```yaml
nowo_performance:
    async: true  # Enables asynchronous storage
    # ... rest of configuration
```

### 2. Configure Messenger

The bundle uses the default message bus. Ensure Messenger is configured:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        # By default, messages are processed synchronously
        # For asynchronous processing, configure a transport
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        
        routing:
            # Optional: route metrics messages to a specific transport
            'Nowo\PerformanceBundle\Message\RecordMetricsMessage': async
```

### 3. Process messages (if using asynchronous transport)

If you configured an asynchronous transport, you need to run the worker:

```bash
php bin/console messenger:consume async -vv
```

Or in production with supervisord/systemd to keep the worker running.

## How It Works

### Synchronous mode (default)

When `async: false` (or not set):

1. Metrics are calculated at the end of the request
2. They are stored immediately in the database
3. The HTTP response is sent after storage

### Asynchronous mode

When `async: true` and Messenger is available:

1. Metrics are calculated at the end of the request
2. A `RecordMetricsMessage` is created with the data
3. The message is dispatched to the message bus
4. The HTTP response is sent immediately (without waiting for storage)
5. The message is processed in the background (synchronously or asynchronously depending on your configuration)

## Benefits of Asynchronous Mode

- **Better performance:** The HTTP response is not blocked waiting for DB storage
- **Scalability:** You can process metrics in separate workers
- **Resilience:** If storage fails, it does not affect the user response
- **Distributed load:** You can distribute metrics processing

## Full Configuration Example

### Development (synchronous for debugging)

```yaml
# config/packages/dev/nowo_performance.yaml
nowo_performance:
    async: false  # Immediate storage to see results instantly
    environments: ['dev']
```

### Production (asynchronous)

```yaml
# config/packages/prod/nowo_performance.yaml
nowo_performance:
    async: true  # Background storage
    environments: ['prod']
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async:
                dsn: 'doctrine://default'
        
        routing:
            'Nowo\PerformanceBundle\Message\RecordMetricsMessage': async
```

## Verification

To verify that asynchronous mode is working:

1. Enable asynchronous mode in configuration
2. Make some requests
3. Check that metrics appear in the database (there may be a short delay)
4. Check Messenger worker logs if using an asynchronous transport

## Automatic Fallback

If `async: true` but Messenger is not available:

- The bundle automatically falls back to synchronous mode
- No errors are raised
- Metrics are stored as usual

This allows the bundle to work without Messenger, while you can opt into asynchronous mode when needed.

## Troubleshooting

### Metrics are not stored

1. Check that Messenger is installed: `composer show symfony/messenger`
2. Check configuration: `async: true` in `nowo_performance.yaml`
3. If using an asynchronous transport, check that the worker is running
4. Check Symfony and Messenger logs

### Metrics are stored with delay

This is normal in asynchronous mode. The delay depends on:
- If using a synchronous transport: almost immediate
- If using an asynchronous transport: depends on worker speed

### I want immediate processing but without blocking

Use Messenger with a synchronous transport (default) and `async: true`. Messages are processed immediately but in a separate context that does not block the HTTP response.
