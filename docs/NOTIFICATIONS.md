# Notification System

The bundle includes a full notification system for performance alerts, supporting multiple channels.

## Available Channels

### 📧 Email

Sends alerts by email using Symfony Mailer.

**Requirements:**
```bash
composer require symfony/mailer
```

**Configuration:**
```yaml
nowo_performance:
    notifications:
        enabled: true
        email:
            enabled: true
            from: 'noreply@example.com'
            to:
                - 'admin@example.com'
                - 'devops@example.com'
```

### 💬 Slack

Sends alerts to Slack via webhooks.

**Requirements:**
```bash
composer require symfony/http-client
```

**Configuration:**
```yaml
nowo_performance:
    notifications:
        enabled: true
        slack:
            enabled: true
            webhook_url: 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
```

**Getting the Webhook URL:**
1. Go to https://api.slack.com/apps
2. Create a new app or select an existing one
3. Go to "Incoming Webhooks"
4. Enable "Incoming Webhooks"
5. Create a new webhook and copy the URL

### 👥 Microsoft Teams

Sends alerts to Microsoft Teams via webhooks.

**Requirements:**
```bash
composer require symfony/http-client
```

**Configuration:**
```yaml
nowo_performance:
    notifications:
        enabled: true
        teams:
            enabled: true
            webhook_url: 'https://outlook.office.com/webhook/YOUR/WEBHOOK/URL'
```

**Getting the Webhook URL:**
1. In Teams, go to the channel where you want to receive notifications
2. Click "..." → "Connectors"
3. Search for "Incoming Webhook"
4. Configure and copy the URL

### 🔗 Generic Webhooks

Sends alerts to any custom webhook.

**Requirements:**
```bash
composer require symfony/http-client
```

**Configuration:**
```yaml
nowo_performance:
    notifications:
        enabled: true
        webhook:
            enabled: true
            url: 'https://your-custom-service.com/webhook'
            format: 'json'  # json, slack, or teams
            headers:
                'X-API-Key': 'your-api-key'
                'Authorization': 'Bearer your-token'
```

## Full Configuration

```yaml
nowo_performance:
    # ... other configuration ...
    
    notifications:
        enabled: true  # Enable/disable all notifications
        
        email:
            enabled: true
            from: 'noreply@example.com'
            to:
                - 'admin@example.com'
        
        slack:
            enabled: true
            webhook_url: 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
        
        teams:
            enabled: true
            webhook_url: 'https://outlook.office.com/webhook/YOUR/WEBHOOK/URL'
        
        webhook:
            enabled: false
            url: ''
            format: 'json'
            headers: []
```

## How It Works

Notifications are sent automatically when:

1. **Request Time** exceeds the configured thresholds
2. **Query Count** exceeds the configured thresholds
3. **Memory Usage** exceeds the configured thresholds

Thresholds are configured in:
```yaml
nowo_performance:
    thresholds:
        request_time:
            warning: 0.5   # seconds
            critical: 1.0  # seconds
        query_count:
            warning: 20
            critical: 50
        memory_usage:
            warning: 20.0  # MB
            critical: 50.0 # MB
```

## Creating Custom Channels

You can create your own notification channels by implementing the `NotificationChannelInterface`:

```php
<?php

use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Entity\RouteData;

class CustomNotificationChannel implements NotificationChannelInterface
{
    public function send(PerformanceAlert $alert, RouteData $routeData): bool
    {
        // Your sending logic here
        return true;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'custom';
    }
}
```

Register your channel in `services.yaml`:

```yaml
services:
    App\Notification\CustomNotificationChannel:
        tags:
            - { name: 'nowo_performance.notification_channel', alias: 'custom' }
```

## Email Template Customization

Emails are rendered using Twig templates that you can customize.

### Default Templates

The bundle includes two templates:
- `@NowoPerformanceBundle/Notification/email_alert.html.twig` — HTML version
- `@NowoPerformanceBundle/Notification/email_alert.txt.twig` — Plain text version

### Customizing Templates

You can override the templates by creating your own versions:

**1. Create the template in your project:**

```
templates/
  bundles/
    NowoPerformanceBundle/
      Notification/
        email_alert.html.twig
        email_alert.txt.twig
```

**2. Variables available in the templates:**

- `alert` — `PerformanceAlert` object with:
  - `alert.message` — Alert message
  - `alert.type` — Alert type (request_time, query_count, etc.)
  - `alert.severity` — Severity (warning, critical)
  - `alert.context` — Array with additional context
- `routeData` — `RouteData` entity with all properties
- `severityColor` — HTML color for severity (#dc3545 for critical, #ffc107 for warning)
- `severityLabel` — Severity label (Critical, Warning)

**3. Example custom template:**

```twig
{# templates/bundles/NowoPerformanceBundle/Notification/email_alert.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* Your custom styles */
    </style>
</head>
<body>
    <h1>{{ severityLabel }} Alert</h1>
    <p>{{ alert.message }}</p>
    
    <h2>Route: {{ routeData.name }}</h2>
    <p>Request Time: {{ routeData.requestTime|number_format(4) }}s</p>
    {# ... more custom content ... #}
</body>
</html>
```

**Note:** If Twig is not available, the bundle uses a simple fallback with basic HTML.

## Alert Formats

### Email

Emails include:
- Title with severity (Warning/Critical)
- Full route information
- Table with all metrics
- Alert context
- Customizable templates using Twig

### Slack

Slack message format with:
- Color by severity (yellow for warning, red for critical)
- Fields with route information
- Timestamp

### Teams

Teams MessageCard format with:
- Color by severity
- Section with facts about the route
- Teams-compatible format

### Webhook JSON

Generic JSON format:
```json
{
    "alert": {
        "type": "request_time",
        "severity": "critical",
        "message": "Critical: Route 'app_home' has request time of 1.2345s",
        "context": {
            "value": 1.2345,
            "threshold": 1.0
        }
    },
    "route": {
        "name": "app_home",
        "env": "prod",
        "http_method": "GET",
        "request_time": 1.2345,
        "query_count": 15,
        "query_time": 0.5,
        "memory_usage": 1048576,
        "access_count": 100,
        "last_accessed_at": "2026-01-26T10:30:00+00:00"
    },
    "timestamp": "2026-01-26T10:30:00+00:00"
}
```

## Disabling Notifications

To disable all notifications:

```yaml
nowo_performance:
    notifications:
        enabled: false
```

Or disable individual channels:

```yaml
nowo_performance:
    notifications:
        enabled: true
        email:
            enabled: false
        slack:
            enabled: true
```

## Dynamic Configuration from Database

If you need to store notification credentials in the database instead of the YAML file, you can use a service to configure channels dynamically.

### Option 1: Using Compiler Pass (Recommended for static configuration)

This approach registers channels during container compilation. Useful when configuration does not change frequently.

**1. Create the dynamic configuration service:**

```php
<?php
// src/Service/DynamicNotificationConfiguration.php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DynamicNotificationConfiguration
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?MailerInterface $mailer = null,
        private readonly ?HttpClientInterface $httpClient = null
    ) {
    }

    private function getNotificationConfigFromDatabase(): array
    {
        // Get configuration from your entity/table
        $settings = $this->entityManager
            ->getRepository('App\Entity\NotificationSettings')
            ->findOneBy(['key' => 'performance_notifications']);
        
        if ($settings && $settings->getValue()) {
            return json_decode($settings->getValue(), true) ?? [];
        }
        
        return ['enabled' => false];
    }

    public function createChannelsFromDatabase(): array
    {
        $config = $this->getNotificationConfigFromDatabase();
        $channels = [];

        // Email
        if ($config['email']['enabled'] ?? false) {
            $channels[] = new EmailNotificationChannel(
                $this->mailer,
                $config['email']['from'] ?? 'noreply@example.com',
                $config['email']['to'] ?? [],
                true
            );
        }

        // Slack
        if ($config['slack']['enabled'] ?? false && !empty($config['slack']['webhook_url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['slack']['webhook_url'],
                'slack',
                [],
                true
            );
        }

        // Teams
        if ($config['teams']['enabled'] ?? false && !empty($config['teams']['webhook_url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['teams']['webhook_url'],
                'teams',
                [],
                true
            );
        }

        // Generic webhook
        if ($config['webhook']['enabled'] ?? false && !empty($config['webhook']['url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['webhook']['url'],
                $config['webhook']['format'] ?? 'json',
                $config['webhook']['headers'] ?? [],
                true
            );
        }

        return $channels;
    }

    public function areNotificationsEnabled(): bool
    {
        $config = $this->getNotificationConfigFromDatabase();
        return $config['enabled'] ?? false;
    }
}
```

**2. Create the Compiler Pass:**

```php
<?php
// src/DependencyInjection/Compiler/NotificationCompilerPass.php

namespace App\DependencyInjection\Compiler;

use App\Service\DynamicNotificationConfiguration;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class NotificationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_performance.notifications.enabled')) {
            return;
        }

        try {
            $configService = new DynamicNotificationConfiguration(
                $container->get('doctrine.orm.entity_manager'),
                $container->has('mailer.mailer') ? $container->get('mailer.mailer') : null,
                $container->has('http_client') ? $container->get('http_client') : null
            );

            $channels = $configService->createChannelsFromDatabase();

            foreach ($channels as $channel) {
                $serviceId = sprintf('nowo_performance.notification.channel.dynamic.%s', $channel->getName());
                $definition = new Definition(get_class($channel));
                
                // Configure arguments according to channel type...
                // (see full example in docs/examples/NotificationCompilerPass.php)
                
                $definition->setPublic(true);
                $definition->addTag('nowo_performance.notification_channel', [
                    'alias' => $channel->getName()
                ]);
                
                $container->setDefinition($serviceId, $definition);
            }
        } catch (\Exception $e) {
            // Fall back to default YAML configuration on failure
            error_log('Error loading notification config from database: ' . $e->getMessage());
        }
    }
}
```

**3. Register the Compiler Pass in your Bundle:**

```php
<?php
// src/Kernel.php or src/YourBundle.php

use App\DependencyInjection\Compiler\NotificationCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Kernel extends BaseKernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new NotificationCompilerPass());
    }
}
```

### Option 2: Using Factory Service (Recommended for dynamic configuration)

This approach creates channels on demand. Useful when credentials change frequently.

**1. Create the Factory Service:**

```php
<?php
// src/Service/NotificationFactoryService.php

namespace App\Service;

use Nowo\PerformanceBundle\Service\NotificationService;

class NotificationFactoryService
{
    public function __construct(
        private readonly DynamicNotificationConfiguration $configService
    ) {
    }

    public function createNotificationService(): NotificationService
    {
        $channels = $this->configService->createChannelsFromDatabase();
        $enabled = $this->configService->areNotificationsEnabled();

        return new NotificationService($channels, $enabled);
    }
}
```

**2. Register the Factory Service:**

```yaml
# config/services.yaml
services:
    App\Service\DynamicNotificationConfiguration:
        public: true
    
    App\Service\NotificationFactoryService:
        arguments:
            $configService: '@App\Service\DynamicNotificationConfiguration'
        public: true
```

**3. Use the Factory Service in your code:**

```php
<?php
// In an EventListener or Service

use App\Service\NotificationFactoryService;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;

class PerformanceAlertListener
{
    public function __construct(
        private readonly NotificationFactoryService $notificationFactory
    ) {
    }

    public function onAfterMetricsRecorded(AfterMetricsRecordedEvent $event): void
    {
        // Create NotificationService with up-to-date config from DB
        $notificationService = $this->notificationFactory->createNotificationService();
        
        // Use the service as usual
        if ($event->getRouteData()->getRequestTime() > 1.0) {
            $alert = new PerformanceAlert(/* ... */);
            $notificationService->sendAlert($alert, $event->getRouteData());
        }
    }
}
```

### Recommended Database Structure

Example entity for storing configuration:

```php
<?php
// src/Entity/NotificationSettings.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_settings')]
class NotificationSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', unique: true)]
    private string $key;

    #[ORM\Column(type: 'text')]
    private string $value;

    // Getters and setters...
}
```

**Example stored JSON value:**

```json
{
    "enabled": true,
    "email": {
        "enabled": true,
        "from": "noreply@example.com",
        "to": ["admin@example.com", "devops@example.com"]
    },
    "slack": {
        "enabled": true,
        "webhook_url": "https://hooks.slack.com/services/YOUR/WEBHOOK/URL"
    },
    "teams": {
        "enabled": false,
        "webhook_url": ""
    },
    "webhook": {
        "enabled": true,
        "url": "https://api.example.com/webhook",
        "format": "json",
        "headers": {
            "X-API-Key": "secret-key"
        }
    }
}
```

### Full Examples

See full, documented examples in:
- `docs/examples/DynamicNotificationConfiguration.php` — Configuration service
- `docs/examples/NotificationCompilerPass.php` — Full Compiler Pass
- `docs/examples/NotificationFactoryService.php` — Full Factory Service

## Troubleshooting

### Notifications are not sent

1. Check that `notifications.enabled: true`
2. Check that the specific channel is enabled
3. Check that dependencies are installed (mailer/http-client)
4. Check logs for errors

### Email does not work

- Check Symfony Mailer configuration
- Check that `from` and `to` are set
- Review SMTP configuration

### Webhooks do not work

- Check that the webhook URL is correct
- Check that `symfony/http-client` is installed
- Check logs for HTTP errors
- Test the webhook URL manually

### Dynamic configuration does not work

- **Compiler Pass:** Ensure the DB is available during `cache:clear`
- **Factory Service:** Ensure the service is correctly injected
- Check logs for DB connection errors
- Verify the JSON structure in the DB is correct
